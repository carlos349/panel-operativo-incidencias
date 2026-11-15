{{-- Control de formulario polimórfico: input (por defecto), select o textarea con un único estilo. --}}
@props(['as' => 'input', 'invalid' => false])

@php
    $classes = $attributes->class([
        'w-full rounded-lg border-0 bg-surface px-3 text-base text-ink shadow-sm ring-1 ring-inset sm:text-sm',
        $as === 'select' ? 'flex items-center justify-between gap-2' : 'block',
        'placeholder:text-ink-subtle focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-focus',
        $as === 'textarea' ? 'py-2.5' : 'min-h-11',
        // El panel de opciones del select se estiliza en resources/css/app.css (.ui-select).
        'ui-select cursor-pointer' => $as === 'select',
        $invalid ? 'ring-danger-solid' : 'ring-line-strong',
    ])->merge($invalid ? ['aria-invalid' => 'true'] : []);
@endphp

@if ($as === 'input')
    <input {{ $classes->merge(['type' => 'text']) }}>
@else
    <{{ $as }} {{ $classes }}>{{ $slot }}</{{ $as }}>
@endif
