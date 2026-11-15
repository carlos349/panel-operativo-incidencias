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
    public function an_incident_without_assignee_can_only_be_created_open(): void
    {
        $this->expectException(IncidentRuleViolation::class);

        $this->createIncident(status: IncidentStatus::InReview);
    }

    #[Test]
    public function an_incident_with_assignee_can_be_created_in_review_but_not_beyond(): void
    {
        $incident = $this->createIncident(status: IncidentStatus::InReview, assignee: 'Ana Beltrán');

        $this->assertSame(IncidentStatus::InReview, $incident->status->type());
        $this->assertNotNull($incident->first_responded_at);

        $this->expectException(IncidentRuleViolation::class);

        $this->createIncident(status: IncidentStatus::Resolved, assignee: 'Ana Beltrán');
    }

    #[Test]
    public function any_unresolved_incident_can_be_cancelled_with_a_reason(): void
    {
        foreach ([[], [IncidentStatus::InReview], [IncidentStatus::InReview, IncidentStatus::Blocked]] as $path) {
            $incident = $this->createIncident();

            foreach ($path as $step) {
                $this->move($incident, $step);
            }

            $this->move($incident, IncidentStatus::Cancelled, 'Duplicada');

            $this->assertSame(IncidentStatus::Cancelled, $incident->fresh()->status->type());
            $this->assertDatabaseHas('incident_logs', [
                'incident_id' => $incident->id,
                'new_value' => IncidentStatus::Cancelled->value,
            ]);
        }
    }

    #[Test]
    public function cancelling_requires_a_reason(): void
    {
        $incident = $this->createIncident();

        $this->expectException(IncidentRuleViolation::class);

        $this->move($incident, IncidentStatus::Cancelled);
    }

    #[Test]
    public function a_resolved_incident_cannot_be_cancelled_and_cancelled_is_final(): void
    {
        $resolved = $this->createIncident();
        $this->move($resolved, IncidentStatus::InReview);
        $this->move($resolved, IncidentStatus::Resolved);

        $this->assertFalse(IncidentStatus::Resolved->canTransitionTo(IncidentStatus::Cancelled));
        $this->assertSame([], IncidentStatus::Cancelled->allowedTransitions());

        $this->expectException(IncidentRuleViolation::class);

        $this->move($resolved, IncidentStatus::Cancelled, 'No procede');
    }

    #[Test]
    public function the_board_renders(): void
    {
        $this->createIncident();

        $this->get('/')->assertOk()->assertSee('Incidencia de prueba');
    }

    private function createIncident(
        string $priority = 'P3',
        IncidentStatus $status = IncidentStatus::Open,
        ?string $assignee = null,
    ): Incident {
        return $this->service->create([
            'title' => 'Incidencia de prueba',
            'description' => 'Descripción suficientemente larga.',
            'status_id' => Status::findByType($status)->id,
            'priority' => $priority,
            'assigned_to' => $assignee,
        ], 'Tester')->load('status');
    }

    private function move(Incident $incident, IncidentStatus $to, ?string $reason = null): void
    {
        $this->service->changeStatus($incident, Status::findByType($to), 'Tester', $reason);
    }
}
