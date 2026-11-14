{{-- Envoltorio de campo: etiqueta + control (slot) + mensaje de error. --}}
@props(['label', 'for', 'error' => null, 'required' => false, 'hint' => null])

<div {{ $attributes }}>
    <label for="{{ $for }}" class="mb-1.5 block text-sm font-medium text-slate-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-600" aria-hidden="true">*</span>
            <span class="sr-only">(obligatorio)</span>
        @endif
    </label>

    {{ $slot }}

    @if ($error)
        <p class="mt-1.5 text-sm text-red-700" role="alert">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
