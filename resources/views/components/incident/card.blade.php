{{-- Tarjeta del tablero. Recibe un App\ViewModels\IncidentCard, nunca el modelo Eloquent. --}}
@props(['card'])

@php
    // Vencida y bloqueada son las dos situaciones que deben "saltar a la vista".
    $accent = match (true) {
        $card->isOverdue => 'border-red-300 border-l-red-600 bg-red-50 ring-1 ring-red-200',
        $card->isBlocked => 'border-rose-300 border-l-rose-700 bg-rose-50',
        $card->isResolved => 'border-slate-200 border-l-emerald-500 bg-white',
        default => 'border-slate-200 border-l-slate-300 bg-white',
    };
@endphp

<article
    {{ $attributes->class(['rounded-xl border border-l-4 shadow-sm transition-shadow hover:shadow-md lg:cursor-grab', $accent]) }}
    data-incident-id="{{ $card->id }}"
>
    <button
        type="button"
        class="block w-full cursor-pointer rounded-t-xl p-3 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
        wire:click="$dispatch('incident-selected', { incidentId: {{ $card->id }} })"
        aria-label="Ver detalle de {{ $card->reference }}: {{ $card->title }}"
    >
        <div class="flex items-center justify-between gap-2">
            <span class="font-mono text-xs font-medium text-slate-500">{{ $card->reference }}</span>
            <x-ui.badge :tone="$card->priorityTone" :solid="$card->priorityTone === 'danger'">{{ $card->priorityLabel }}</x-ui.badge>
        </div>

        <h3 class="mt-2 line-clamp-2 text-sm font-semibold leading-snug text-slate-900">{{ $card->title }}</h3>

        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            <x-ui.badge :tone="$card->statusTone">
                @if ($card->isBlocked)
                    <x-ui.icon name="lock" class="size-3.5!" />
                @endif
                {{ $card->statusName }}
            </x-ui.badge>

            @if ($card->slaLabel)
                <x-ui.badge :tone="$card->slaTone" :solid="$card->isOverdue" :pulse="$card->isOverdue">{{ $card->slaLabel }}</x-ui.badge>
            @endif
        </div>

        <dl class="mt-3 space-y-1 text-xs text-slate-600">
            <div class="flex items-center gap-1.5">
                <dt class="sr-only">Responsable</dt>
                <x-ui.icon name="user" class="size-4! text-slate-400" />
                <dd @class(['truncate', 'italic text-slate-500' => $card->isUnassigned])>{{ $card->assignee }}</dd>
            </div>
            <div class="flex items-center gap-1.5">
                <dt class="sr-only">Fecha de creación</dt>
                <x-ui.icon name="clock" class="size-4! text-slate-400" />
                <dd><time title="{{ $card->createdAgo }}">{{ $card->createdAt }}</time></dd>
            </div>
            @if ($card->slaDetail)
                <div @class(['flex items-center gap-1.5', 'font-semibold text-red-700' => $card->isOverdue])>
                    <dt class="sr-only">SLA</dt>
                    <x-ui.icon name="alert" @class(['size-4!', 'text-slate-400' => ! $card->isOverdue]) />
                    <dd>{{ $card->slaDetail }}</dd>
                </div>
            @endif
        </dl>
    </button>

    {{-- Móvil / táctil: el cambio de estado se hace con un desplegable en lugar de arrastrar. --}}
    @if ($card->transitions)
        <div class="border-t border-black/5 p-2 lg:hidden">
            <label for="move-{{ $card->id }}" class="sr-only">Cambiar estado de {{ $card->reference }}</label>
            <x-ui.control
                as="select"
                id="move-{{ $card->id }}"
                x-on:change="$wire.requestStatusChange({{ $card->id }}, Number($event.target.value)); $event.target.value = ''"
            >
                <option value="">Cambiar estado…</option>
                @foreach ($card->transitions as $transition)
                    <option value="{{ $transition['id'] }}">Pasar a {{ $transition['name'] }}</option>
                @endforeach
            </x-ui.control>
        </div>
    @endif
</article>
