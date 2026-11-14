{{-- Botón polimórfico: `as="a"` lo convierte en enlace conservando el mismo aspecto. --}}
@props(['as' => 'button', 'variant' => 'primary', 'size' => 'md'])

@php
    $variants = [
        'primary' => 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-indigo-600',
        'secondary' => 'bg-white text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus-visible:outline-indigo-600',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-500 focus-visible:outline-red-600',
        'success' => 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-emerald-600',
        'ghost' => 'text-slate-600 hover:bg-slate-200/70 hover:text-slate-900 focus-visible:outline-indigo-600',
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
        'focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]) }}>
    {{ $slot }}
</{{ $as }}>
