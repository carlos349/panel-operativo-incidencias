{{-- Selector de tema. La preferencia vive en el navegador (localStorage), no en el servidor. --}}
<div wire:ignore x-data="themeSwitcher" {{ $attributes }}>
    <x-ui.field label="Tema" for="theme">
        <x-ui.control as="select" id="theme" x-model="theme">
            @foreach (config('themes.available') as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </x-ui.control>
    </x-ui.field>
</div>
