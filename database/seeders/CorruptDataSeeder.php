<?php

namespace Database\Seeders;

use App\Enums\IncidentStatus;
use App\Enums\LogAction;
use App\Models\Category;
use App\Models\Status;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Simula datos dañados por escrituras que se saltaron la aplicación
 * (scripts manuales, importaciones, un despliegue defectuoso...).
 *
 * Inserta directamente con el query builder, sin pasar por IncidentService,
 * precisamente para romper las garantías que el servicio ofrece.
 * La herramienta `php artisan incidents:repair` los detecta y sanea.
 */
class CorruptDataSeeder extends Seeder
{
    private CarbonImmutable $now;

    public function run(): void
    {
        $this->call(CatalogSeeder::class);
        $this->now = CarbonImmutable::now();

        $this->resolvedWithoutResolutionLog();
        $this->resolvedWithoutLogNorDate();
        $this->statusAheadOfLastLog();
        $this->statusContradictsLastLog();
        $this->duplicatedLog();
        $this->logBeforeIncidentExisted();
    }

    /** Caso 1 (autocorregible): «Resuelta» con fecha de resolución, pero sin log de resolución. */
    private function resolvedWithoutResolutionLog(): void
    {
        $created = $this->now->subDays(5);

        $id = $this->incident('[Inconsistente 1A] Resuelta sin log de resolución', IncidentStatus::Resolved, $created, [
            'resolution_date' => $created->addHours(5),
            'updated_at' => $created->addHours(5),
        ]);

        $this->log($id, LogAction::Created, null, IncidentStatus::Open, $created);
        $this->log($id, LogAction::StatusChanged, IncidentStatus::Open, IncidentStatus::InReview, $created->addHour());
    }

    /** Caso 1 (revisión manual): «Resuelta» sin log y además sin fecha de resolución. */
    private function resolvedWithoutLogNorDate(): void
    {
        $created = $this->now->subDays(4);

        $id = $this->incident('[Inconsistente 1B] Resuelta sin log ni fecha de resolución', IncidentStatus::Resolved, $created, [
            'updated_at' => $created->addHours(2),
        ]);

        $this->log($id, LogAction::Created, null, IncidentStatus::Open, $created);
        $this->log($id, LogAction::StatusChanged, IncidentStatus::Open, IncidentStatus::InReview, $created->addHour());
    }

    /** Caso 2 (autocorregible): el estado avanzó un paso válido, pero falta su log. */
    private function statusAheadOfLastLog(): void
    {
        $created = $this->now->subDays(2);

        $id = $this->incident('[Inconsistente 2A] Estado adelantado respecto a su último log', IncidentStatus::InReview, $created, [
            'updated_at' => $created->addHours(3),
        ]);

        $this->log($id, LogAction::Created, null, IncidentStatus::Open, $created);
    }

    /** Caso 2 (revisión manual): el último log dice «Resuelta», pero la incidencia está «Bloqueada». */
    private function statusContradictsLastLog(): void
    {
        $created = $this->now->subDays(6);

        $id = $this->incident('[Inconsistente 2B] Estado contradice a su último log', IncidentStatus::Blocked, $created, [
            'updated_at' => $created->addDay(),
        ]);

        $this->log($id, LogAction::Created, null, IncidentStatus::Open, $created);
        $this->log($id, LogAction::StatusChanged, IncidentStatus::Open, IncidentStatus::InReview, $created->addHour());
        $this->log($id, LogAction::StatusChanged, IncidentStatus::InReview, IncidentStatus::Resolved, $created->addHours(4));
    }

    /** Caso 3 (autocorregible): el mismo log insertado dos veces. */
    private function duplicatedLog(): void
    {
        $created = $this->now->subDays(3);

        $id = $this->incident('[Inconsistente 3A] Log de cambio de estado duplicado', IncidentStatus::InReview, $created, [
            'updated_at' => $created->addHours(2),
        ]);

        $this->log($id, LogAction::Created, null, IncidentStatus::Open, $created);
        $this->log($id, LogAction::StatusChanged, IncidentStatus::Open, IncidentStatus::InReview, $created->addHours(2));
        $this->log($id, LogAction::StatusChanged, IncidentStatus::Open, IncidentStatus::InReview, $created->addHours(2));
    }

    /** Caso 3 (revisión manual): un log fechado antes de que existiera la incidencia. */
    private function logBeforeIncidentExisted(): void
    {
        $created = $this->now->subDays(2);

        $id = $this->incident('[Inconsistente 3B] Log anterior a la creación de la incidencia', IncidentStatus::InReview, $created, [
            'updated_at' => $created->addHours(2),
        ]);

        $this->log($id, LogAction::Created, null, IncidentStatus::Open, $created);
        $this->log($id, LogAction::StatusChanged, IncidentStatus::Open, IncidentStatus::InReview, $created->addHours(2));
        $this->log($id, LogAction::TitleChanged, 'Título previo', 'Título imposible', $created->subDays(3));
    }

    /** @param  array<string, mixed>  $overrides */
    private function incident(string $title, IncidentStatus $status, CarbonImmutable $created, array $overrides = []): int
    {
        return DB::table('incidents')->insertGetId(array_merge([
            'title' => $title,
            'description' => 'Registro generado por CorruptDataSeeder para el caso práctico de saneamiento.',
            'status_id' => Status::findByType($status)->id,
            'priority' => 'P3',
            'category_id' => Category::query()->where('name', 'Bug Informático')->value('id'),
            'requester_name' => 'Importación heredada',
            'assigned_to' => null,
            'resolution_date' => null,
            'first_response_due_at' => $created->addHours(4),
            'first_responded_at' => $created->addHour(),
            'resolution_due_at' => $created->addDays(5),
            'created_at' => $created,
            'updated_at' => $created,
        ], $overrides));
    }

    private function log(int $incidentId, LogAction $action, IncidentStatus|string|null $old, IncidentStatus|string|null $new, CarbonImmutable $at): void
    {
        DB::table('incident_logs')->insert([
            'incident_id' => $incidentId,
            'action' => $action->value,
            'old_value' => $old instanceof IncidentStatus ? $old->value : $old,
            'new_value' => $new instanceof IncidentStatus ? $new->value : $new,
            'user_name' => 'script-migracion',
            'metadata' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
