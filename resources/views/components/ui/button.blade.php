{{-- Botón polimórfico: `as="a"` lo convierte en enlace conservando el mismo aspecto. --}}
@props(['as' => 'button', 'variant' => 'primary', 'size' => 'md'])

@php
    $variants = [
        'primary' => 'bg-primary text-on-primary shadow-sm hover:bg-primary-hover',
        'secondary' => 'bg-surface text-ink shadow-sm ring-1 ring-inset ring-line-strong hover:bg-surface-muted',
        'danger' => 'bg-danger-solid text-on-danger shadow-sm hover:opacity-90',
        'success' => 'bg-success-solid text-on-success shadow-sm hover:opacity-90',
        'neutral' => 'bg-neutral-solid text-on-neutral shadow-sm hover:opacity-90',
        'ghost' => 'text-ink-muted hover:bg-surface-muted hover:text-ink',
    ];

    $sizes = [
        'sm' => 'min-h-9 px-3 text-sm',
        'md' => 'min-h-11 px-4 text-sm',
        'icon' => 'size-11 justify-center',
    ];
@endphp

<{{ $as }} {{ $attributes
    ->merge($as === 'button' ? ['type' => 'button'] : [])
    ->class([
        'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg font-semibold transition-colors',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
        'disabled:cursor-not-allowed disabled:opacity-50',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]) }}>
    {{ $slot }}
</{{ $as }}>
