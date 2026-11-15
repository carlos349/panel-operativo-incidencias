{{-- Envoltorio de campo: etiqueta + control (slot) + mensaje de error. --}}
@props(['label', 'for', 'error' => null, 'required' => false, 'hint' => null])

<div {{ $attributes }}>
    <label for="{{ $for }}" class="mb-1.5 block text-sm font-medium text-ink">
        {{ $label }}
        @if ($required)
            <span class="text-danger-ink" aria-hidden="true">*</span>
            <span class="sr-only">(obligatorio)</span>
        @endif
    </label>

    {{ $slot }}

    @if ($error)
        <p class="mt-1.5 text-sm text-danger-ink" role="alert">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-ink-muted">{{ $hint }}</p>
    @endif
</div>
