<?php

namespace App\Livewire;

use App\Enums\IncidentStatus;
use App\Enums\Priority;
use App\Models\Category;
use App\Models\Status;
use App\Services\IncidentService;
use App\Support\CurrentOperator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateIncident extends Component
{
    public bool $open = false;

    public string $title = '';

    public string $description = '';

    public string $statusId = '';

    public string $priority = '';

    public string $categoryId = '';

    public string $requesterName = '';

    public string $assignedTo = '';

    #[On('create-incident')]
    public function show(): void
    {
        $this->resetForm();
        $this->statusId = (string) Status::findByType(IncidentStatus::Open)->id;
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function save(IncidentService $service): void
    {
        $data = $this->validate();

        $incident = $service->create([
            'title' => $data['title'],
            'description' => $data['description'],
            'status_id' => (int) $data['statusId'],
            'priority' => $data['priority'],
            'category_id' => $data['categoryId'] !== '' ? (int) $data['categoryId'] : null,
            'requester_name' => $data['requesterName'] ?: null,
            'assigned_to' => $data['assignedTo'] ?: null,
        ], CurrentOperator::name());

        $this->close();
        $this->dispatch('incident-saved', incidentId: $incident->id);
        $this->dispatch('notify', message: 'Incidencia creada correctamente.', tone: 'success');
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            // Sin responsable solo «Abierta»; con responsable, también «En Revisión».
            'statusId' => ['required', Rule::in($this->initialStatuses()->pluck('id')->all())],
            'priority' => ['required', Rule::enum(Priority::class)],
            'categoryId' => ['nullable', Rule::exists('categories', 'id')],
            'requesterName' => ['nullable', 'string', 'max:100'],
            'assignedTo' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.',
            'max' => 'El campo :attribute no puede superar los :max caracteres.',
            'exists' => 'El valor elegido en :attribute no es válido.',
            'statusId.in' => 'Sin responsable la incidencia solo puede crearse «Abierta»; con responsable, «Abierta» o «En Revisión».',
            'enum' => 'El valor elegido en :attribute no es válido.',
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'title' => 'título',
            'description' => 'descripción',
            'statusId' => 'estado',
            'priority' => 'prioridad',
            'categoryId' => 'categoría',
            'requesterName' => 'solicitante',
            'assignedTo' => 'responsable',
        ];
    }

    public function render(): View
    {
        return view('livewire.create-incident', [
            'statuses' => $this->open ? $this->initialStatuses() : collect(),
            'canStartInReview' => filled(trim($this->assignedTo)),
            'categories' => $this->open ? Category::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'priorities' => Priority::cases(),
        ]);
    }

    /** Si se quita el responsable, «En Revisión» deja de ser válido: se vuelve a «Abierta». */
    public function updatedAssignedTo(): void
    {
        if (! $this->initialStatuses()->contains('id', (int) $this->statusId)) {
            $this->statusId = (string) Status::findByType(IncidentStatus::Open)->id;
        }
    }

    /**
     * Estados con los que puede crearse la incidencia según tenga o no responsable.
     *
     * @return Collection<int, Status>
     */
    private function initialStatuses(): Collection
    {
        $allowed = array_map(
            fn (IncidentStatus $status) => $status->value,
            IncidentStatus::allowedInitial(filled(trim($this->assignedTo))),
        );

        return Status::query()->whereIn('name', $allowed)->orderBy('id')->get(['id', 'name']);
    }

    private function resetForm(): void
    {
        $this->reset('title', 'description', 'statusId', 'priority', 'categoryId', 'requesterName', 'assignedTo');
        $this->resetValidation();
    }
}
