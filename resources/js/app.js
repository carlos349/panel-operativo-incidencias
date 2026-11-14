import Sortable from 'sortablejs';

// Livewire ya incluye Alpine: solo registramos comportamientos sobre él.
document.addEventListener('alpine:init', () => {
    /**
     * Columna del tablero con arrastrar y soltar (solo escritorio con puntero fino;
     * en móvil el cambio de estado se hace con el desplegable de la tarjeta).
     *
     * El DOM nunca decide: al soltar se devuelve la tarjeta a su sitio y se pide
     * el cambio al servidor, que valida la transición, pide confirmación si hace
     * falta y vuelve a pintar el tablero.
     */
    window.Alpine.data('kanbanColumn', () => ({
        sortable: null,

        init() {
            if (!window.matchMedia('(min-width: 1024px) and (pointer: fine)').matches) {
                return;
            }

            this.sortable = Sortable.create(this.$el, {
                group: 'incidents',
                draggable: '[data-incident-id]',
                animation: 150,
                sort: false,
                ghostClass: 'opacity-40',
                onEnd: (event) => {
                    if (event.from === event.to) {
                        return;
                    }

                    const incidentId = Number(event.item.dataset.incidentId);
                    const statusId = Number(event.to.dataset.statusId);

                    event.from.insertBefore(event.item, event.from.children[event.oldIndex] ?? null);
                    this.$wire.requestStatusChange(incidentId, statusId);
                },
            });
        },

        destroy() {
            this.sortable?.destroy();
        },
    }));

    window.Alpine.data('toasts', () => ({
        items: [],

        push({ message, tone = 'success' }) {
            const id = Date.now() + Math.random();

            this.items.push({ id, message, tone });
            setTimeout(() => {
                this.items = this.items.filter((toast) => toast.id !== id);
            }, tone === 'danger' ? 6000 : 3500);
        },
    }));
});
