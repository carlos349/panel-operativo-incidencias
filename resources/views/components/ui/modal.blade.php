{{--
    Contenedor modal por composición: slots `title`, por defecto (cuerpo) y `footer`.
    - variant="dialog": centrado (hoja inferior en móvil).
    - variant="panel":  panel lateral a toda altura (pantalla completa en móvil).
    `close` es el nombre de la acción Livewire que lo cierra.
--}}
@props(['variant' => 'dialog', 'close' => 'close', 'labelledby' => 'modal-title'])

@php
    $isPanel = $variant === 'panel';
@endphp

<div
    data-modal
    class="fixed inset-0 z-40 flex {{ $isPanel ? 'justify-end' : 'items-end justify-center sm:items-center sm:p-4' }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $labelledby }}"
    x-data
    x-on:keydown.escape.window="if ($el === Array.from(document.querySelectorAll('[data-modal]')).at(-1)) $wire.{{ $close }}()"
>
    <div class="absolute inset-0 bg-slate-900/50" wire:click="{{ $close }}" aria-hidden="true"></div>

    <div {{ $attributes->class([
        'relative flex w-full flex-col bg-white shadow-xl',
        'h-full max-w-2xl' => $isPanel,
        'max-h-[92dvh] rounded-t-2xl sm:max-w-lg sm:rounded-2xl' => ! $isPanel,
    ]) }}>
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-6">
            <div id="{{ $labelledby }}" class="min-w-0 flex-1">{{ $title }}</div>

            <x-ui.button variant="ghost" size="icon" class="-mr-2 shrink-0" wire:click="{{ $close }}" aria-label="Cerrar">
                <x-ui.icon name="x" />
            </x-ui.button>
        </header>

        <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-6">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:justify-end sm:px-6 {{ $isPanel ? '' : 'sm:rounded-b-2xl' }}">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
