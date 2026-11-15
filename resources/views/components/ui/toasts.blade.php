{{-- Avisos efímeros. Cualquier componente Livewire los lanza con dispatch('notify', message, tone). --}}
<div
    x-data="toasts"
    x-on:notify.window="push($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4"
    aria-live="polite"
>
    <template x-for="toast in items" :key="toast.id">
        <div
            x-transition.opacity.duration.200ms
            class="pointer-events-auto flex max-w-md items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium shadow-lg"
            :class="toast.tone === 'danger' ? 'bg-danger-solid text-on-danger' : 'bg-ink text-surface'"
            :role="toast.tone === 'danger' ? 'alert' : 'status'"
        >
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>
