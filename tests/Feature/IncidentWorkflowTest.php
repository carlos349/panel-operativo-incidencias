<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\LogAction;
use App\Exceptions\IncidentRuleViolation;
use App\Models\Incident;
use App\Models\Status;
use App\Services\IncidentService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private IncidentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
        $this->service = app(IncidentService::class);
        Carbon::setTestNow('2026-11-16 09:00:00'); // lunes
    }

    #[Test]
    public function creating_an_incident_logs_it_and_computes_sla_deadlines(): void
    {
        $incident = $this->createIncident('P1');

        $this->assertSame('2026-11-16 09:15', $incident->first_response_due_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-16 13:00', $incident->resolution_due_at->format('Y-m-d H:i'));
        $this->assertDatabaseHas('incident_logs', [
            'incident_id' => $incident->id,
            'action' => LogAction::Created->value,
            'new_value' => IncidentStatus::Open->value,
        ]);
    }

    #[Test]
    public function every_status_change_is_logged(): void
    {
        $incident = $this->createIncident();

        $this->move($incident, IncidentStatus::InReview);
        $this->move($incident, IncidentStatus::Resolved);

        $incident->refresh();

        $this->assertNotNull($incident->resolution_date);
        $this->assertNotNull($incident->first_responded_at);
        $this->assertSame(
            [IncidentStatus::Open->value, IncidentStatus::InReview->value, IncidentStatus::Resolved->value],
            $incident->logs()->orderBy('id')->pluck('new_value')->all(),
        );
    }

    #[Test]
    public function transitions_outside_the_flow_are_rejected_and_leave_no_trace(): void
    {
        $incident = $this->createIncident();

        try {
            $this->move($incident, IncidentStatus::Resolved);
            $this->fail('Se esperaba una violación de regla de negocio.');
        } catch (IncidentRuleViolation) {
            $this->assertSame(IncidentStatus::Open, $incident->fresh()->status->type());
            $this->assertSame(1, $incident->logs()->count());
        }
    }

    #[Test]
    public function reopening_requires_a_reason_and_leaves_an_explicit_log(): void
    {
        $incident = $this->createIncident();
        $this->move($incident, IncidentStatus::InReview);
        $this->move($incident, IncidentStatus::Resolved);

        try {
            $this->move($incident, IncidentStatus::Open);
            $this->fail('La reapertura sin motivo debería rechazarse.');
        } catch (IncidentRuleViolation) {
            $this->assertSame(IncidentStatus::Resolved, $incident->fresh()->status->type());
        }

        $this->move($incident, IncidentStatus::Open, 'El problema persiste');

        $this->assertNull($incident->fresh()->resolution_date);
        $this->assertDatabaseHas('incident_logs', [
            'incident_id' => $incident->id,
            'action' => LogAction::Reopened->value,
            'old_value' => IncidentStatus::Resolved->value,
            'new_value' => IncidentStatus::Open->value,
        ]);
    }

    #[Test]
    public function the_board_renders(): void
    {
        $this->createIncident();

        $this->get('/')->assertOk()->assertSee('Incidencia de prueba');
    }

    private function createIncident(string $priority = 'P3'): Incident
    {
        return $this->service->create([
            'title' => 'Incidencia de prueba',
            'description' => 'Descripción suficientemente larga.',
            'status_id' => Status::findByType(IncidentStatus::Open)->id,
            'priority' => $priority,
        ], 'Tester');
    }

    private function move(Incident $incident, IncidentStatus $to, ?string $reason = null): void
    {
        $this->service->changeStatus($incident, Status::findByType($to), 'Tester', $reason);
    }
}
