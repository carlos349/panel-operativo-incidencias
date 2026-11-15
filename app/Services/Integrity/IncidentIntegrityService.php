<?php

namespace App\Services\Integrity;

use App\Enums\IncidentStatus;
use App\Enums\LogAction;
use App\Models\IncidentLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Diagnóstico y saneamiento de inconsistencias entre incidents e incident_logs.
 *
 * Criterio general: solo se autocorrige cuando los propios datos aportan
 * evidencia suficiente para reconstruir lo ocurrido. Si hay que inventar
 * información (quién, cuándo o por qué), la incidencia se marca para
 * revisión manual y no se toca nada más.
 */
class IncidentIntegrityService
{
    public const SYSTEM_USER = 'sistema:saneamiento';

    public const RESOLVED_WITHOUT_LOG = 'resolved_without_log';

    public const STATUS_LOG_MISMATCH = 'status_log_mismatch';

    public const DUPLICATED_LOG = 'duplicated_log';

    public const LOG_BEFORE_INCIDENT = 'log_before_incident';

    /** @return Collection<int, Finding> */
    public function scan(): Collection
    {
        return collect()
            ->concat($this->resolvedWithoutResolutionLog())
            ->concat($this->statusNotMatchingLastLog())
            ->concat($this->duplicatedLogs())
            ->concat($this->logsBeforeIncidentCreation())
            ->values();
    }

    /** Aplica la acción prevista para un hallazgo dentro de una transacción. */
    public function apply(Finding $finding): void
    {
        if (! $finding->isActionable()) {
            return;
        }

        DB::transaction(function () use ($finding) {
            // Bloquea la incidencia: nadie debe modificarla mientras se sanea.
            DB::table('incidents')->where('id', $finding->incidentId)->lockForUpdate()->first();

            if ($finding->resolution === Finding::MANUAL) {
                $this->flagForManualReview($finding);

                return;
            }

            match ($finding->code) {
                self::RESOLVED_WITHOUT_LOG, self::STATUS_LOG_MISMATCH => $this->insertMissingStatusLog($finding),
                self::DUPLICATED_LOG => $this->removeDuplicates($finding),
            };
        });
    }

    /**
     * 1. Incidencias «Resuelta» sin ningún log que las lleve a ese estado.
     *
     * @return Collection<int, Finding>
     */
    private function resolvedWithoutResolutionLog(): Collection
    {
        $rows = DB::table('incidents as i')
            ->join('statuses as s', 's.id', '=', 'i.status_id')
            ->where('s.name', IncidentStatus::Resolved->value)
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('incident_logs as l')
                ->whereColumn('l.incident_id', 'i.id')
                ->whereIn('l.action', LogAction::statusBearing())
                ->where('l.new_value', IncidentStatus::Resolved->value))
            ->orderBy('i.id')
            ->get(['i.id', 'i.resolution_date']);

        return $rows->map(function (object $row) {
            $problem = 'Está «Resuelta» pero no existe log de resolución.';
            $last = $this->lastStatusLog($row->id);

            if ($row->resolution_date === null) {
                return $this->manual(self::RESOLVED_WITHOUT_LOG, $row->id, $problem,
                    'Tampoco tiene resolution_date: no hay evidencia de cuándo ni si realmente se resolvió.');
            }

            if ($last !== null && $last->created_at > $row->resolution_date) {
                return $this->manual(self::RESOLVED_WITHOUT_LOG, $row->id, $problem,
                    'Hay logs de estado posteriores a resolution_date: la cronología es ambigua.');
            }

            return new Finding(
                self::RESOLVED_WITHOUT_LOG, $row->id, $problem, Finding::AUTO,
                "Crear el log de resolución faltante con fecha {$row->resolution_date} (resolution_date).",
                ['old' => $last?->new_value, 'new' => IncidentStatus::Resolved->value, 'at' => $row->resolution_date],
            );
        });
    }

    /**
     * 2. Incidencias cuyo estado actual difiere del new_value de su último log de estado.
     *    (Las «Resuelta» sin log de resolución ya las cubre el caso 1.)
     *
     * @return Collection<int, Finding>
     */
    private function statusNotMatchingLastLog(): Collection
    {
        $actions = "'".implode("','", LogAction::statusBearing())."'";

        $rows = DB::table('incidents as i')
            ->join('statuses as s', 's.id', '=', 'i.status_id')
            ->join('incident_logs as l', 'l.id', '=', DB::raw(
                "(select l2.id from incident_logs l2
                  where l2.incident_id = i.id and l2.action in ({$actions})
                  order by l2.created_at desc, l2.id desc limit 1)"
            ))
            ->whereColumn('l.new_value', '<>', 's.name')
            ->orderBy('i.id')
            ->get(['i.id', 'i.updated_at', 's.name as current_status', 'l.new_value as logged_status', 'l.created_at as logged_at']);

        return $rows
            ->map(function (object $row) {
                $problem = "Estado actual «{$row->current_status}», pero su último log dice «{$row->logged_status}».";
                $logged = IncidentStatus::tryFrom((string) $row->logged_status);
                $current = IncidentStatus::from($row->current_status);

                if ($current === IncidentStatus::Resolved && ! $this->hasResolutionLog($row->id)) {
                    return null; // caso 1
                }

                if ($logged === null || ! $logged->canTransitionTo($current)) {
                    return $this->manual(self::STATUS_LOG_MISMATCH, $row->id, $problem,
                        'El salto no es una transición válida del flujo: no se puede saber si el dato erróneo es el estado o el log.');
                }

                if ($current->requiresReasonFrom($logged)) {
                    return $this->manual(self::STATUS_LOG_MISMATCH, $row->id, $problem,
                        'Sería una reapertura o una anulación, que exigen un motivo explícito que no se puede inventar.');
                }

                // Un único paso válido del flujo: lo más probable es un cambio que no dejó log.
                $at = max($row->updated_at, CarbonImmutable::parse($row->logged_at)->addSecond()->toDateTimeString());

                return new Finding(
                    self::STATUS_LOG_MISMATCH, $row->id, $problem, Finding::AUTO,
                    "Crear el log faltante «{$logged->value}» → «{$current->value}» con fecha {$at} (updated_at).",
                    ['old' => $logged->value, 'new' => $current->value, 'at' => $at],
                );
            })
            ->filter()
            ->values();
    }

    /**
     * 3a. Logs duplicados: misma incidencia, acción, valores, usuario e instante.
     *
     * @return Collection<int, Finding>
     */
    private function duplicatedLogs(): Collection
    {
        $groups = DB::table('incident_logs')
            ->select('incident_id', 'action', 'user_name', 'created_at')
            ->selectRaw('min(id) as keep_id, count(*) as copies')
            ->groupBy('incident_id', 'action', 'old_value', 'new_value', 'user_name', 'created_at')
            ->havingRaw('count(*) > 1')
            ->orderBy('incident_id')
            ->get();

        return $groups->map(function (object $group) {
            $keep = IncidentLog::query()->findOrFail($group->keep_id);

            $duplicateIds = DB::table('incident_logs')
                ->where('incident_id', $keep->incident_id)
                ->where('action', $keep->action)
                ->where('user_name', $keep->user_name)
                ->where('created_at', $keep->created_at)
                ->whereRaw('old_value <=> ?', [$keep->old_value])
                ->whereRaw('new_value <=> ?', [$keep->new_value])
                ->where('id', '<>', $keep->id)
                ->pluck('id')
                ->all();

            $ids = implode(', ', $duplicateIds);

            return new Finding(
                self::DUPLICATED_LOG, $keep->incident_id,
                "El log #{$keep->id} ({$keep->action}) está repetido {$group->copies} veces.",
                Finding::AUTO,
                "Conservar el log #{$keep->id} y eliminar las copias idénticas (#{$ids}), dejando constancia en el historial.",
                ['keep_id' => $keep->id, 'duplicate_ids' => $duplicateIds],
            );
        });
    }

    /**
     * 3b. Logs fechados antes de que existiera su incidencia.
     *
     * @return Collection<int, Finding>
     */
    private function logsBeforeIncidentCreation(): Collection
    {
        $rows = DB::table('incident_logs as l')
            ->join('incidents as i', 'i.id', '=', 'l.incident_id')
            ->whereColumn('l.created_at', '<', 'i.created_at')
            ->orderBy('l.id')
            ->get(['l.id', 'l.incident_id', 'l.action', 'l.created_at', 'i.created_at as incident_created_at']);

        return $rows->map(fn (object $row) => $this->manual(
            self::LOG_BEFORE_INCIDENT, $row->incident_id,
            "El log #{$row->id} ({$row->action}) está fechado el {$row->created_at}, antes de crearse la incidencia ({$row->incident_created_at}).",
            'No se sabe si la fecha es errónea o si el log pertenece a otra incidencia; borrarlo o refecharlo destruiría evidencia.',
            ['log_id' => $row->id],
        ));
    }

    private function insertMissingStatusLog(Finding $finding): void
    {
        IncidentLog::query()->forceCreate([
            'incident_id' => $finding->incidentId,
            'action' => LogAction::StatusChanged->value,
            'old_value' => $finding->context['old'],
            'new_value' => $finding->context['new'],
            'user_name' => self::SYSTEM_USER,
            'metadata' => [
                'repair' => $finding->code,
                'inferred' => true,
                'note' => 'Log reconstruido por el saneamiento; el autor real del cambio es desconocido.',
                'repaired_at' => now()->toDateTimeString(),
            ],
            'created_at' => $finding->context['at'],
            'updated_at' => now(),
        ]);
    }

    private function removeDuplicates(Finding $finding): void
    {
        $removed = IncidentLog::query()->whereIn('id', $finding->context['duplicate_ids'])->get();

        IncidentLog::query()->whereIn('id', $removed->modelKeys())->delete();

        IncidentLog::query()->create([
            'incident_id' => $finding->incidentId,
            'action' => LogAction::DataRepair->value,
            'old_value' => null,
            'new_value' => 'Logs duplicados eliminados',
            'user_name' => self::SYSTEM_USER,
            'metadata' => [
                'repair' => $finding->code,
                'kept_log_id' => $finding->context['keep_id'],
                // Copia de lo eliminado: el saneamiento no destruye información.
                'removed_logs' => $removed->map->only(['id', 'action', 'old_value', 'new_value', 'user_name', 'created_at'])->all(),
            ],
        ]);
    }

    private function flagForManualReview(Finding $finding): void
    {
        IncidentLog::query()->create([
            'incident_id' => $finding->incidentId,
            'action' => LogAction::ManualReview->value,
            'old_value' => null,
            'new_value' => $finding->problem,
            'user_name' => self::SYSTEM_USER,
            'metadata' => ['code' => $finding->code, 'why_not_auto' => $finding->plan] + $finding->context,
        ]);
    }

    /** @param  array<string, mixed>  $context */
    private function manual(string $code, int $incidentId, string $problem, string $why, array $context = []): Finding
    {
        $alreadyFlagged = IncidentLog::query()
            ->where('incident_id', $incidentId)
            ->where('action', LogAction::ManualReview->value)
            ->where('metadata->code', $code)
            ->exists();

        return new Finding(
            $code, $incidentId, $problem,
            $alreadyFlagged ? Finding::ALREADY_FLAGGED : Finding::MANUAL,
            $alreadyFlagged ? "Ya marcada para revisión manual. {$why}" : "Marcar para revisión manual. {$why}",
            $context,
        );
    }

    private function lastStatusLog(int $incidentId): ?object
    {
        return DB::table('incident_logs')
            ->where('incident_id', $incidentId)
            ->whereIn('action', LogAction::statusBearing())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['new_value', 'created_at']);
    }

    private function hasResolutionLog(int $incidentId): bool
    {
        return DB::table('incident_logs')
            ->where('incident_id', $incidentId)
            ->whereIn('action', LogAction::statusBearing())
            ->where('new_value', IncidentStatus::Resolved->value)
            ->exists();
    }
}
