<div>
    @if ($open)
        <x-ui.modal labelledby="create-title">
            <x-slot:title>
                <h2 class="text-lg font-semibold text-slate-900">Nueva incidencia</h2>
                <p class="text-sm text-slate-500">Los campos marcados con * son obligatorios.</p>
            </x-slot:title>

            <form id="create-incident-form" wire:submit="save" class="grid gap-4 sm:grid-cols-2" novalidate>
                <x-ui.field label="Título" for="new-title" required :error="$errors->first('title')" class="sm:col-span-2">
                    <x-ui.control id="new-title" wire:model="title" maxlength="150" :invalid="$errors->has('title')" autofocus />
                </x-ui.field>

                <x-ui.field label="Descripción" for="new-description" required :error="$errors->first('description')" class="sm:col-span-2">
                    <x-ui.control as="textarea" id="new-description" rows="4" wire:model="description" :invalid="$errors->has('description')"></x-ui.control>
                </x-ui.field>

                <x-ui.field label="Estado" for="new-status" required :error="$errors->first('statusId')">
                    <x-ui.control as="select" id="new-status" wire:model="statusId" :invalid="$errors->has('statusId')">
                        <option value="">Selecciona…</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                    </x-ui.control>
                </x-ui.field>

                <x-ui.field label="Prioridad" for="new-priority" required :error="$errors->first('priority')" hint="Determina los plazos de SLA.">
                    <x-ui.control as="select" id="new-priority" wire:model="priority" :invalid="$errors->has('priority')">
                        <option value="">Selecciona…</option>
                        @foreach ($priorities as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-ui.control>
                </x-ui.field>

                <x-ui.field label="Categoría" for="new-category" :error="$errors->first('categoryId')" class="sm:col-span-2">
                    <x-ui.control as="select" id="new-category" wire:model="categoryId">
                        <option value="">Sin categoría</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </x-ui.control>
                </x-ui.field>

                <x-ui.field label="Solicitante" for="new-requester" :error="$errors->first('requesterName')">
                    <x-ui.control id="new-requester" wire:model="requesterName" maxlength="100" />
                </x-ui.field>

                <x-ui.field label="Responsable" for="new-assignee" :error="$errors->first('assignedTo')">
                    <x-ui.control id="new-assignee" wire:model="assignedTo" maxlength="100" />
                </x-ui.field>
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="close">Cancelar</x-ui.button>
                <x-ui.button type="submit" form="create-incident-form" wire:loading.attr="disabled" wire:target="save">
                    Crear incidencia
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
