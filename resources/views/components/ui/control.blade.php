{{-- Control de formulario polimórfico: input (por defecto), select o textarea con un único estilo. --}}
@props(['as' => 'input', 'invalid' => false])

@php
    $classes = $attributes->class([
        'block w-full rounded-lg border-0 bg-white px-3 text-base text-slate-900 shadow-sm ring-1 ring-inset sm:text-sm',
        'placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-indigo-600',
        $as === 'textarea' ? 'py-2.5' : 'min-h-11',
        $invalid ? 'ring-red-400' : 'ring-slate-300',
    ])->merge($invalid ? ['aria-invalid' => 'true'] : []);
@endphp

@if ($as === 'input')
    <input {{ $classes->merge(['type' => 'text']) }}>
@else
    <{{ $as }} {{ $classes }}>{{ $slot }}</{{ $as }}>
@endif
