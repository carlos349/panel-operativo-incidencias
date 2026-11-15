<?php

namespace App\ViewModels;

use App\Enums\IncidentStatus;
use App\Enums\SlaState;
use App\Models\Incident;
use App\Models\Status;
use App\Support\Sla\SlaPolicy;
use Illuminate\Support\Collection;

/**
 * Lo que la UI necesita saber de una incidencia, ya traducido a lenguaje de
 * presentación. Las vistas nunca reciben el modelo Eloquent ni sus columnas.
 */
final readonly class IncidentCard
{
    /**
     * @param  list<array{id: int, name: string}>  $transitions  Estados a los que puede pasar
     */
    public function __construct(
        public int $id,
        public string $reference,
        public string $title,
        public string $description,
        public int $statusId,
        public string $statusName,
        public string $statusTone,
        public string $priorityLabel,
        public string $priorityTone,
        public ?string $category,
        public string $assignee,
        public bool $isUnassigned,
        public string $requester,
        public string $createdAt,
        public string $createdAgo,
        public ?string $slaLabel,
        public ?string $slaTone,
        public ?string $slaDetail,
        public bool $isOverdue,
        public bool $isBlocked,
        public bool $isResolved,
        public bool $isCancelled,
        public array $transitions,
    ) {}

    /** @param  Collection<int, Status>  $statuses */
    public static function from(Incident $incident, SlaPolicy $sla, Collection $statuses): self
    {
        $status = $incident->status->type();
        // Una incidencia anulada queda fuera del SLA: ni cumple ni incumple.
        $slaState = $status === IncidentStatus::Cancelled
            ? null
            : $sla->resolutionState($incident->priority, $incident->resolution_due_at, $incident->resolution_date);

        return new self(
            id: $incident->id,
            reference: 'INC-'.str_pad((string) $incident->id, 4, '0', STR_PAD_LEFT),
            title: $incident->title,
            description: $incident->description,
            statusId: $incident->status_id,
            statusName: $status->value,
            statusTone: $status->tone(),
            priorityLabel: $incident->priority->label(),
            priorityTone: $incident->priority->tone(),
            category: $incident->category?->name,
            assignee: $incident->assigned_to ?: 'Sin asignar',
            isUnassigned: blank($incident->assigned_to),
            requester: $incident->requester_name ?: 'No indicado',
            createdAt: $incident->created_at->format('d/m/Y H:i'),
            createdAgo: $incident->created_at->diffForHumans(),
            slaLabel: $slaState?->label(),
            slaTone: $slaState?->tone(),
            slaDetail: self::slaDetail($incident, $slaState),
            isOverdue: $slaState === SlaState::Breached,
            isBlocked: $status === IncidentStatus::Blocked,
            isResolved: $status === IncidentStatus::Resolved,
            isCancelled: $status === IncidentStatus::Cancelled,
            transitions: $statuses
                ->filter(fn (Status $candidate) => $status->canTransitionTo($candidate->type()))
                ->map(fn (Status $candidate) => ['id' => $candidate->id, 'name' => $candidate->name])
                ->values()
                ->all(),
        );
    }

    private static function slaDetail(Incident $incident, ?SlaState $state): ?string
    {
        return match ($state) {
            null => null,
            SlaState::Met, SlaState::Missed => 'Resuelta el '.$incident->resolution_date->format('d/m/Y H:i'),
            SlaState::Breached => 'Venció '.$incident->resolution_due_at->diffForHumans(),
            default => 'Vence el '.$incident->resolution_due_at->format('d/m/Y H:i'),
        };
    }
}
