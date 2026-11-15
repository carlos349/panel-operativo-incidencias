{{--
    Etiqueta de estado. Polimórfica: `as` decide la etiqueta HTML (span, button, a...).
    `tone` es un valor semántico (neutral|info|success|warning|danger); el color lo pone el tema activo.
--}}
@props(['as' => 'span', 'tone' => 'neutral', 'solid' => false, 'pulse' => false])

@php
    $tones = $solid
        ? [
            'neutral' => 'bg-neutral-solid text-on-neutral ring-neutral-solid',
            'info' => 'bg-info-solid text-on-info ring-info-solid',
            'success' => 'bg-success-solid text-on-success ring-success-solid',
            'warning' => 'bg-warning-solid text-on-warning ring-warning-solid',
            'danger' => 'bg-danger-solid text-on-danger ring-danger-solid',
        ]
        : [
            'neutral' => 'bg-neutral-soft text-neutral-ink ring-neutral-line',
            'info' => 'bg-info-soft text-info-ink ring-info-line',
            'success' => 'bg-success-soft text-success-ink ring-success-line',
            'warning' => 'bg-warning-soft text-warning-ink ring-warning-line',
            'danger' => 'bg-danger-soft text-danger-ink ring-danger-line',
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
