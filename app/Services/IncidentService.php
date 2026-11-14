<?php

namespace App\Services;

use App\Enums\IncidentStatus;
use App\Enums\LogAction;
use App\Enums\Priority;
use App\Exceptions\IncidentRuleViolation;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\Status;
use App\Support\Sla\SlaPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura sobre incidencias. Cada operación modifica la
 * incidencia e inserta su registro en incident_logs dentro de la misma
 * transacción: o se guardan ambos, o ninguno.
 */
class IncidentService
{
    public function __construct(private readonly SlaPolicy $sla) {}

    /**
     * @param  array{title: string, description: string, status_id: int, priority: string, category_id?: int|null, requester_name?: string|null, assigned_to?: string|null}  $data
     */
    public function create(array $data, string $actor): Incident
    {
        return DB::transaction(function () use ($data, $actor) {
            $status = Status::query()->findOrFail($data['status_id']);
            $priority = Priority::from($data['priority']);
            $now = now()->toImmutable();

            $incident = Incident::query()->create([
                'title' => $data['title'],
                'description' => $data['description'],
                'status_id' => $status->id,
                'priority' => $priority,
                'category_id' => $data['category_id'] ?? null,
                'requester_name' => $data['requester_name'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'first_response_due_at' => $this->sla->firstResponseDueAt($priority, $now),
                'resolution_due_at' => $this->sla->resolutionDueAt($priority, $now),
                // Si nace fuera de «Abierta», alguien ya la está atendiendo.
                'first_responded_at' => $status->type() === IncidentStatus::Open ? null : $now,
                'resolution_date' => $status->type() === IncidentStatus::Resolved ? $now : null,
            ]);

            $this->log($incident, LogAction::Created, null, $status->name, $actor, [
                'priority' => $priority->value,
            ]);

            return $incident;
        });
    }

    /**
     * Edita título y/o descripción. Genera un log por cada campo que cambia.
     *
     * @param  array{title?: string, description?: string}  $data
     */
    public function updateDetails(Incident $incident, array $data, string $actor): Incident
    {
        return DB::transaction(function () use ($incident, $data, $actor) {
            $incident = $this->lock($incident);

            $fields = [
                'title' => LogAction::TitleChanged,
                'description' => LogAction::DescriptionChanged,
            ];

            foreach ($fields as $field => $action) {
                if (! array_key_exists($field, $data) || $data[$field] === $incident->{$field}) {
                    continue;
                }

                $old = $incident->{$field};
                $incident->{$field} = $data[$field];
                $this->log($incident, $action, $old, $data[$field], $actor);
            }

            $incident->save();

            return $incident;
        });
    }

    public function changeStatus(Incident $incident, Status $target, string $actor, ?string $reason = null): Incident
    {
        return DB::transaction(function () use ($incident, $target, $actor, $reason) {
            $incident = $this->lock($incident);
            $from = $incident->status->type();
            $to = $target->type();
            $reason = trim((string) $reason) ?: null;

            if ($from === $to) {
                throw IncidentRuleViolation::sameStatus($to->value);
            }

            if (! $from->canTransitionTo($to)) {
                throw IncidentRuleViolation::invalidTransition($from->value, $to->value);
            }

            $isReopen = $to->isReopenFrom($from);

            if ($isReopen && $reason === null) {
                throw IncidentRuleViolation::reopenReasonRequired();
            }

            $now = now()->toImmutable();
            $metadata = array_filter(['reason' => $reason]);

            $incident->status_id = $target->id;

            if ($to === IncidentStatus::Resolved) {
                $incident->resolution_date = $now;
            }

            if ($isReopen) {
                // La reapertura reinicia el plazo de resolución desde este momento.
                $metadata['previous_resolution_date'] = $incident->resolution_date?->toDateTimeString();
                $incident->resolution_date = null;
                $incident->resolution_due_at = $this->sla->resolutionDueAt($incident->priority, $now);
            }

            if ($incident->first_responded_at === null) {
                $incident->first_responded_at = $now;
                $metadata['first_response'] = true;
            }

            $incident->save();

            $this->log(
                $incident,
                $isReopen ? LogAction::Reopened : LogAction::StatusChanged,
                $from->value,
                $to->value,
                $actor,
                $metadata,
            );

            return $incident->setRelation('status', $target);
        });
    }

    public function addComment(Incident $incident, string $author, string $body): IncidentComment
    {
        return DB::transaction(function () use ($incident, $author, $body) {
            $incident = $this->lock($incident);

            $comment = $incident->comments()->create([
                'author_name' => $author,
                'body' => $body,
            ]);

            // El primer comentario cuenta como primera respuesta del SLA.
            if ($incident->first_responded_at === null) {
                $incident->first_responded_at = now();
                $incident->save();

                $this->log($incident, LogAction::FirstResponse, null, 'Comentario', $author, [
                    'comment_id' => $comment->id,
                ]);
            }

            return $comment;
        });
    }

    /** Relee la fila con bloqueo para evitar escrituras concurrentes sobre datos obsoletos. */
    private function lock(Incident $incident): Incident
    {
        return Incident::query()->with('status')->lockForUpdate()->findOrFail($incident->id);
    }

    /** @param  array<string, mixed>  $metadata */
    private function log(Incident $incident, LogAction $action, ?string $old, ?string $new, string $actor, array $metadata = []): void
    {
        $incident->logs()->create([
            'action' => $action->value,
            'old_value' => $old,
            'new_value' => $new,
            'user_name' => $actor,
            'metadata' => $metadata ?: null,
        ]);
    }
}
