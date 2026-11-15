<div class="mx-auto max-w-[96rem] px-4 pb-16 sm:px-6 lg:px-8">
    {{-- Cabecera --}}
    <header class="flex flex-col gap-4 py-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ config('app.name') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-ink-muted">
                Seguimiento en tiempo real de las incidencias abiertas, su prioridad y el cumplimiento de SLA
                (horario de oficina: lunes a viernes, 08:00–17:00).
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <x-ui.theme-switcher class="sm:w-44" />

            <x-ui.field label="Actuando como" for="operator" class="sm:w-52">
                <x-ui.control id="operator" wire:model.blur="operator" maxlength="100" autocomplete="name" />
            </x-ui.field>

            <x-ui.button wire:click="$dispatch('create-incident')">
                <x-ui.icon name="plus" />
                Nueva incidencia
            </x-ui.button>
        </div>
    </header>

    {{-- Contadores por estado: también actúan como filtro --}}
    <section aria-label="Incidencias por estado" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        @foreach ($counters as $counter)
            @php
                $dot = [
                    'info' => 'bg-info-solid',
                    'warning' => 'bg-warning-solid',
                    'danger' => 'bg-danger-solid',
                    'success' => 'bg-success-solid',
                ][$counter['tone']] ?? 'bg-ink-subtle';
            @endphp

            <button
                type="button"
                wire:key="counter-{{ $counter['id'] }}"
                wire:click="toggleStatusFilter({{ $counter['id'] }})"
                aria-pressed="{{ $counter['active'] ? 'true' : 'false' }}"
                @class([
                    'cursor-pointer rounded-xl border bg-surface p-4 text-left shadow-sm transition-colors',
                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                    'border-primary ring-2 ring-focus' => $counter['active'],
                    'border-line hover:border-line-strong hover:bg-surface-muted' => ! $counter['active'],
                ])
            >
                <span class="flex items-center gap-2 text-sm font-medium text-ink-muted">
                    <span class="size-2.5 rounded-full {{ $dot }}" aria-hidden="true"></span>
                    {{ $counter['name'] }}
                </span>
                <span class="mt-1 block text-3xl font-bold tabular-nums text-ink">{{ $counter['total'] }}</span>
                <span class="text-xs text-ink-muted">{{ $counter['active'] ? 'Filtro activo · pulsa para quitar' : 'Pulsa para filtrar' }}</span>
            </button>
        @endforeach
    </section>

    {{-- Filtros --}}
    <section aria-label="Filtros" class="mt-4 rounded-xl border border-line bg-surface p-3 shadow-sm sm:p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_14rem_14rem_auto] lg:items-end">
            <x-ui.field label="Buscar" for="search" class="sm:col-span-2 lg:col-span-1">
                <div class="relative">
                    <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-subtle" />
                    <x-ui.control
                        id="search"
                        type="search"
                        class="pl-10"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Título, descripción, solicitante o responsable"
                    />
                </div>
            </x-ui.field>

            <x-ui.field label="Estado" for="status-filter">
                <x-ui.control as="select" id="status-filter" wire:model.live="statusFilter">
                    <option value="">Todos los estados</option>
                    @foreach ($counters as $counter)
                        <option value="{{ $counter['id'] }}">{{ $counter['name'] }}</option>
                    @endforeach
                </x-ui.control>
            </x-ui.field>

            <x-ui.field label="Prioridad" for="priority-filter">
                <x-ui.control as="select" id="priority-filter" wire:model.live="priorityFilter">
                    <option value="">Todas las prioridades</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                    @endforeach
                </x-ui.control>
            </x-ui.field>

            <x-ui.button variant="secondary" wire:click="clearFilters" :disabled="! $hasFilters">Limpiar</x-ui.button>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-ink-muted">
            <span aria-live="polite">{{ $totalVisible }} {{ $totalVisible === 1 ? 'incidencia' : 'incidencias' }}</span>

            @if ($alerts['overdue'] > 0)
                <x-ui.badge tone="danger" solid pulse>{{ $alerts['overdue'] }} con SLA vencido</x-ui.badge>
            @endif

            @if ($alerts['blocked'] > 0)
                <x-ui.badge tone="danger"><x-ui.icon name="lock" class="size-3.5!" />{{ $alerts['blocked'] }} bloqueadas</x-ui.badge>
            @endif

            <span wire:loading.delay class="text-ink-muted">Actualizando…</span>
        </div>
    </section>

    {{-- Tablero --}}
    <section
        aria-label="Tablero de incidencias"
        @class([
            'mt-4 grid items-start gap-4',
            'md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5' => $columns->count() > 1,
        ])
    >
        @foreach ($columns as $column)
            <div wire:key="column-{{ $column['id'] }}" class="relative rounded-xl bg-lane p-2">
                <h2 class="flex items-center justify-between px-2 py-2 text-sm font-semibold text-ink">
                    {{ $column['name'] }}
                    <x-ui.badge :tone="$column['tone']">{{ $column['cards']->count() }}</x-ui.badge>
                </h2>

                <div
                    x-data="kanbanColumn"
                    data-status-id="{{ $column['id'] }}"
                    @class([
                        'grid min-h-24 content-start gap-2 rounded-lg',
                        'sm:grid-cols-2 xl:grid-cols-3' => $columns->count() === 1,
                    ])
                >
                    @foreach ($column['cards'] as $card)
                        <x-incident.card :card="$card" wire:key="card-{{ $card->id }}" />
                    @endforeach
                </div>

                @if ($column['cards']->isEmpty())
                    <p class="pointer-events-none absolute inset-x-3 top-16 text-center text-sm text-ink-muted">
                        {{ $hasFilters ? 'Sin incidencias con estos filtros.' : 'Sin incidencias en este estado.' }}
                    </p>
                @endif
            </div>
        @endforeach
    </section>

    <livewire:create-incident />
    <livewire:incident-detail />

    {{-- Confirmación explícita antes de bloquear, resolver o reabrir --}}
    @if ($confirmation)
        <x-ui.modal close="cancelStatusChange" labelledby="confirm-title">
            <x-slot:title>
                <h2 class="text-lg font-semibold text-ink">{{ $confirmation['title'] }}</h2>
            </x-slot:title>

            <p class="text-sm font-medium text-ink">{{ $confirmation['incidentTitle'] }}</p>

            <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-ink-muted">
                <x-ui.badge>{{ $confirmation['from'] }}</x-ui.badge>
                <span aria-hidden="true">→</span>
                <span class="sr-only">pasa a</span>
                <x-ui.badge :tone="$confirmation['tone']">{{ $confirmation['to'] }}</x-ui.badge>
            </p>

            <p class="mt-3 text-sm text-ink-muted">{{ $confirmation['message'] }}</p>

            <x-ui.field
                class="mt-4"
                for="pending-reason"
                :label="$confirmation['reasonRequired'] ? 'Motivo' : 'Motivo (opcional)'"
                :required="$confirmation['reasonRequired']"
                :error="$errors->first('pendingReason')"
            >
                <x-ui.control
                    as="textarea"
                    id="pending-reason"
                    rows="3"
                    maxlength="500"
                    wire:model="pendingReason"
                    :invalid="$errors->has('pendingReason')"
                    autofocus
                ></x-ui.control>
            </x-ui.field>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cancelStatusChange">Cancelar</x-ui.button>
                <x-ui.button
                    :variant="['danger' => 'danger', 'success' => 'success', 'neutral' => 'neutral'][$confirmation['tone']] ?? 'primary'"
                    wire:click="confirmStatusChange"
                    wire:loading.attr="disabled"
                >
                    Sí, {{ $confirmation['isReopen'] ? 'reabrir' : 'pasar a '.$confirmation['to'] }}
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
