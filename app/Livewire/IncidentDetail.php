<?php

namespace App\Livewire;

use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\IncidentLog;
use App\Models\Status;
use App\Services\IncidentService;
use App\Support\CurrentOperator;
use App\Support\Sla\SlaPolicy;
use App\ViewModels\IncidentCard;
use App\ViewModels\TimelineEntry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class IncidentDetail extends Component
{
    public ?int $incidentId = null;

    public bool $editing = false;

    public string $title = '';

    public string $description = '';

    public string $commentBody = '';

    #[On('incident-selected')]
    public function show(int $incidentId): void
    {
        $this->reset('editing', 'commentBody');
        $this->resetValidation();
        $this->incidentId = $incidentId;
    }

    public function close(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function startEditing(): void
    {
        $incident = Incident::query()->findOrFail($this->incidentId);

        $this->title = $incident->title;
        $this->description = $incident->description;
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->resetValidation();
    }

    public function save(IncidentService $service): void
    {
        $data = $this->validate(
            [
                'title' => ['required', 'string', 'min:5', 'max:150'],
                'description' => ['required', 'string', 'min:10', 'max:5000'],
            ],
            $this->validationMessages(),
            ['title' => 'título', 'description' => 'descripción'],
        );

        $service->updateDetails(Incident::query()->findOrFail($this->incidentId), $data, CurrentOperator::name());

        $this->editing = false;
        $this->dispatch('incident-saved', incidentId: $this->incidentId);
        $this->dispatch('notify', message: 'Cambios guardados.', tone: 'success');
    }

    /** El cambio de estado lo decide el tablero, que centraliza las confirmaciones. */
    public function changeStatus(int $statusId): void
    {
        $this->dispatch('status-change-requested', incidentId: $this->incidentId, statusId: $statusId);
    }

    public function addComment(IncidentService $service): void
    {
        $data = $this->validate(
            ['commentBody' => ['required', 'string', 'min:2', 'max:2000']],
            $this->validationMessages(),
            ['commentBody' => 'comentario'],
        );

        $service->addComment(
            Incident::query()->findOrFail($this->incidentId),
            CurrentOperator::name(),
            $data['commentBody'],
        );

        $this->reset('commentBody');
        $this->dispatch('incident-saved', incidentId: $this->incidentId);
    }

    #[On('incident-updated')]
    public function refreshDetail(): void
    {
        // El re-render recarga la incidencia con su nuevo estado e historial.
    }

    public function render(SlaPolicy $sla): View
    {
        $incident = $this->incidentId === null
            ? null
            : Incident::query()->with(['status', 'category'])->find($this->incidentId);

        if ($incident === null) {
            return view('livewire.incident-detail', ['card' => null]);
        }

        return view('livewire.incident-detail', [
            'card' => IncidentCard::from($incident, $sla, Status::query()->orderBy('id')->get()),
            'firstResponse' => $this->firstResponseSummary($incident),
            'comments' => $incident->comments()->latest()->latest('id')->get()
                ->map(fn (IncidentComment $comment) => [
                    'id' => $comment->id,
                    'author' => $comment->author_name,
                    'body' => $comment->body,
                    'date' => $comment->created_at->format('d/m/Y H:i'),
                ]),
            'timeline' => $incident->logs()->latest()->latest('id')->get()
                ->map(fn (IncidentLog $log) => TimelineEntry::from($log)),
        ]);
    }

    /** @return array{label: string, tone: string} */
    private function firstResponseSummary(Incident $incident): array
    {
        $due = $incident->first_response_due_at;
        $responded = $incident->first_responded_at;

        return match (true) {
            $due === null => ['label' => 'Sin plazo calculado', 'tone' => 'neutral'],
            $responded !== null && $responded <= $due => ['label' => 'En plazo ('.$responded->format('d/m H:i').')', 'tone' => 'success'],
            $responded !== null => ['label' => 'Fuera de plazo ('.$responded->format('d/m H:i').')', 'tone' => 'danger'],
            now() > $due => ['label' => 'Pendiente y vencida', 'tone' => 'danger'],
            default => ['label' => 'Pendiente, hasta '.$due->format('d/m H:i'), 'tone' => 'info'],
        };
    }

    /** @return array<string, string> */
    private function validationMessages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.',
            'max' => 'El campo :attribute no puede superar los :max caracteres.',
        ];
    }
}
