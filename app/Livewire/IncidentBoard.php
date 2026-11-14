<?php

namespace App\Livewire;

use App\Enums\Priority;
use App\Exceptions\IncidentRuleViolation;
use App\Models\Incident;
use App\Models\Status;
use App\Services\IncidentService;
use App\Support\CurrentOperator;
use App\Support\Sla\SlaPolicy;
use App\ViewModels\IncidentCard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class IncidentBoard extends Component
{
    #[Url(as: 'estado', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'prioridad', except: '')]
    public string $priorityFilter = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public string $operator = '';

    /** Cambio de estado a la espera de confirmación explícita. */
    public ?int $pendingIncidentId = null;

    public ?int $pendingStatusId = null;

    public string $pendingReason = '';

    public function mount(): void
    {
        $this->operator = CurrentOperator::name();
    }

    public function updatedOperator(string $value): void
    {
        CurrentOperator::set($value);
        $this->operator = CurrentOperator::name();
    }

    public function toggleStatusFilter(int $statusId): void
    {
        $this->statusFilter = $this->statusFilter === (string) $statusId ? '' : (string) $statusId;
    }

    public function clearFilters(): void
    {
        $this->reset('statusFilter', 'priorityFilter', 'search');
    }

    /**
     * Punto de entrada único para cambiar de estado (drag & drop, desplegable
     * móvil o vista detallada). Decide si hace falta confirmación.
     */
    #[On('status-change-requested')]
    public function requestStatusChange(int $incidentId, int $statusId, IncidentService $service): void
    {
        $incident = Incident::query()->with('status')->find($incidentId);
        $target = $this->statuses->firstWhere('id', $statusId);

        if ($incident === null || $target === null) {
            return;
        }

        $from = $incident->status->type();
        $to = $target->type();

        if ($from === $to) {
            return;
        }

        if (! $from->canTransitionTo($to)) {
            $this->notify(IncidentRuleViolation::invalidTransition($from->value, $to->value)->getMessage(), 'danger');

            return;
        }

        if ($to->requiresConfirmation() || $to->isReopenFrom($from)) {
            $this->pendingIncidentId = $incident->id;
            $this->pendingStatusId = $target->id;
            $this->pendingReason = '';
            $this->resetValidation();

            return;
        }

        $this->applyStatusChange($service, $incident, $target);
    }

    public function confirmStatusChange(IncidentService $service): void
    {
        $incident = Incident::query()->with('status')->find($this->pendingIncidentId);
        $target = $this->statuses->firstWhere('id', $this->pendingStatusId);

        if ($incident === null || $target === null) {
            $this->cancelStatusChange();

            return;
        }

        $isReopen = $target->type()->isReopenFrom($incident->status->type());

        $this->validate(
            ['pendingReason' => [$isReopen ? 'required' : 'nullable', 'string', 'max:500']],
            ['pendingReason.required' => 'Indica el motivo de la reapertura.'],
        );

        $this->applyStatusChange($service, $incident, $target, $this->pendingReason);
        $this->cancelStatusChange();
    }

    public function cancelStatusChange(): void
    {
        $this->reset('pendingIncidentId', 'pendingStatusId', 'pendingReason');
        $this->resetValidation();
    }

    #[On('incident-saved')]
    public function refreshBoard(): void
    {
        // El re-render vuelve a consultar el tablero.
    }

    /** @return Collection<int, Status> */
    #[Computed]
    public function statuses(): Collection
    {
        return Status::query()->orderBy('id')->get();
    }

    public function render(SlaPolicy $sla): View
    {
        $base = Incident::query()
            ->search($this->search)
            ->when($this->priorityFilter !== '', fn ($query) => $query->where('priority', $this->priorityFilter));

        // Los contadores respetan búsqueda y prioridad, pero no el filtro de estado:
        // así siguen sirviendo para saltar de un estado a otro.
        $counts = (clone $base)->toBase()
            ->selectRaw('status_id, count(*) as total')
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        $cards = $base
            ->with(['status', 'category'])
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status_id', $this->statusFilter))
            ->orderBy('priority')
            ->orderBy('resolution_due_at')
            ->get()
            ->map(fn (Incident $incident) => IncidentCard::from($incident, $sla, $this->statuses));

        $columns = $this->statuses
            ->when($this->statusFilter !== '', fn ($statuses) => $statuses->where('id', (int) $this->statusFilter))
            ->map(fn (Status $status) => [
                'id' => $status->id,
                'name' => $status->name,
                'tone' => $status->type()->tone(),
                'cards' => $cards->where('statusId', $status->id)->values(),
            ])
            ->values();

        return view('livewire.incident-board', [
            'columns' => $columns,
            'counters' => $this->statuses->map(fn (Status $status) => [
                'id' => $status->id,
                'name' => $status->name,
                'tone' => $status->type()->tone(),
                'total' => (int) ($counts[$status->id] ?? 0),
                'active' => $this->statusFilter === (string) $status->id,
            ]),
            'priorities' => Priority::cases(),
            'totalVisible' => $cards->count(),
            'alerts' => [
                'overdue' => $cards->where('isOverdue', true)->count(),
                'blocked' => $cards->where('isBlocked', true)->count(),
            ],
            'hasFilters' => $this->statusFilter !== '' || $this->priorityFilter !== '' || $this->search !== '',
            'confirmation' => $this->confirmationData($cards),
        ]);
    }

    /**
     * @param  Collection<int, IncidentCard>  $cards
     * @return array<string, mixed>|null
     */
    private function confirmationData(Collection $cards): ?array
    {
        if ($this->pendingIncidentId === null) {
            return null;
        }

        $incident = Incident::query()->with('status')->find($this->pendingIncidentId);
        $target = $this->statuses->firstWhere('id', $this->pendingStatusId);

        if ($incident === null || $target === null) {
            return null;
        }

        $isReopen = $target->type()->isReopenFrom($incident->status->type());

        return [
            'title' => $isReopen ? 'Reabrir incidencia' : "Pasar a «{$target->name}»",
            'incidentTitle' => $incident->title,
            'from' => $incident->status->name,
            'to' => $target->name,
            'tone' => $target->type()->tone(),
            'isReopen' => $isReopen,
            'message' => $isReopen
                ? 'La reapertura quedará registrada en el historial y reinicia el plazo de resolución del SLA.'
                : 'Esta acción quedará registrada en el historial de la incidencia.',
        ];
    }

    private function applyStatusChange(IncidentService $service, Incident $incident, Status $target, ?string $reason = null): void
    {
        try {
            $service->changeStatus($incident, $target, CurrentOperator::name(), $reason);
        } catch (IncidentRuleViolation $violation) {
            $this->notify($violation->getMessage(), 'danger');

            return;
        }

        $this->notify("Incidencia movida a «{$target->name}».");
        $this->dispatch('incident-updated', incidentId: $incident->id);
    }

    private function notify(string $message, string $tone = 'success'): void
    {
        $this->dispatch('notify', message: $message, tone: $tone);
    }
}
