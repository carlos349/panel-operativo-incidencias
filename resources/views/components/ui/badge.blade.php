{{--
    Etiqueta de estado. Polimórfica: `as` decide la etiqueta HTML (span, button, a...).
    `tone` es un valor semántico (neutral|info|success|warning|danger); el color vive solo aquí.
--}}
@props(['as' => 'span', 'tone' => 'neutral', 'solid' => false, 'pulse' => false])

@php
    $tones = $solid
        ? [
            'neutral' => 'bg-slate-700 text-white ring-slate-700',
            'info' => 'bg-sky-600 text-white ring-sky-600',
            'success' => 'bg-emerald-600 text-white ring-emerald-600',
            'warning' => 'bg-amber-500 text-slate-900 ring-amber-500',
            'danger' => 'bg-red-600 text-white ring-red-600',
        ]
        : [
            'neutral' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'info' => 'bg-sky-50 text-sky-800 ring-sky-200',
            'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            'warning' => 'bg-amber-50 text-amber-900 ring-amber-300',
            'danger' => 'bg-red-50 text-red-800 ring-red-200',
        ];
@endphp

<{{ $as }} {{ $attributes->class([
    'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset',
    $tones[$tone] ?? $tones['neutral'],
]) }}>
    @if ($pulse)
        <span class="relative flex size-2" aria-hidden="true">
            <span class="absolute inline-flex size-full rounded-full bg-current opacity-60 motion-safe:animate-ping"></span>
            <span class="relative inline-flex size-2 rounded-full bg-current"></span>
        </span>
    @endif
    {{ $slot }}
</{{ $as }}>
