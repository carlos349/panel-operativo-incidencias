<div>
    @if ($card)
        <x-ui.modal variant="panel" labelledby="detail-title">
            <x-slot:title>
                <p class="font-mono text-xs font-medium text-ink-muted">{{ $card->reference }}</p>
                @unless ($editing)
                    <h2 class="mt-0.5 text-lg font-semibold leading-snug text-ink">{{ $card->title }}</h2>
                @else
                    <h2 class="mt-0.5 text-lg font-semibold text-ink">Editando incidencia</h2>
                @endunless
            </x-slot:title>

            {{-- Resumen --}}
            <div class="flex flex-wrap items-center gap-1.5">
                <x-ui.badge :tone="$card->priorityTone" :solid="$card->priorityTone === 'danger'">{{ $card->priorityLabel }}</x-ui.badge>
                <x-ui.badge :tone="$card->statusTone">{{ $card->statusName }}</x-ui.badge>
                @if ($card->slaLabel)
                    <x-ui.badge :tone="$card->slaTone" :solid="$card->isOverdue" :pulse="$card->isOverdue">{{ $card->slaLabel }}</x-ui.badge>
                @endif
            </div>

            {{-- Título y descripción (edición en línea) --}}
            @if ($editing)
                <form wire:submit="save" class="mt-4 space-y-4" novalidate>
                    <x-ui.field label="Título" for="edit-title" required :error="$errors->first('title')">
                        <x-ui.control id="edit-title" wire:model="title" maxlength="150" :invalid="$errors->has('title')" autofocus />
                    </x-ui.field>

                    <x-ui.field label="Descripción" for="edit-description" required :error="$errors->first('description')">
                        <x-ui.control as="textarea" id="edit-description" rows="6" wire:model="description" :invalid="$errors->has('description')"></x-ui.control>
                    </x-ui.field>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <x-ui.button variant="secondary" wire:click="cancelEditing">Cancelar</x-ui.button>
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Guardar cambios</x-ui.button>
                    </div>
                </form>
            @else
                <div class="mt-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-ink">Descripción</h3>
                        <x-ui.button variant="ghost" size="sm" wire:click="startEditing">
                            <x-ui.icon name="pencil" class="size-4!" />
                            Editar título y descripción
                        </x-ui.button>
                    </div>
                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-ink">{{ $card->description }}</p>
                </div>
            @endif

            {{-- Datos --}}
            <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 rounded-xl bg-surface-muted p-4 text-sm sm:grid-cols-2">
                @foreach ([
                    'Responsable' => $card->assignee,
                    'Solicitante' => $card->requester,
                    'Categoría' => $card->category ?? 'Sin categoría',
                    'Creada' => $card->createdAt.' ('.$card->createdAgo.')',
                    'SLA de resolución' => $card->slaDetail ?? ($card->isCancelled ? 'No aplica (anulada)' : 'Sin plazo calculado'),
                    'Primera respuesta' => $firstResponse['label'],
                ] as $term => $value)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-ink-muted">{{ $term }}</dt>
                        <dd class="mt-0.5 text-ink">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            {{-- Cambio de estado --}}
            <div class="mt-5">
                <h3 class="text-sm font-semibold text-ink">Cambiar estado</h3>
                <div class="mt-2 flex flex-wrap gap-2">
                    @forelse ($card->transitions as $transition)
                        <x-ui.button variant="secondary" size="sm" wire:key="transition-{{ $transition['id'] }}" wire:click="changeStatus({{ $transition['id'] }})">
                            {{ $card->isResolved ? 'Reabrir (pasar a '.$transition['name'].')' : 'Pasar a '.$transition['name'] }}
                        </x-ui.button>
                    @empty
                        <p class="text-sm text-ink-muted">
                            {{ $card->isCancelled ? 'La incidencia está anulada: es un estado final.' : 'No hay transiciones disponibles.' }}
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Pestañas internas --}}
            <div class="mt-6" x-data="{ tab: 'comments' }">
                <div class="flex gap-1 border-b border-line" role="tablist" aria-label="Actividad de la incidencia">
                    @foreach (['comments' => 'Comentarios ('.$comments->count().')', 'history' => 'Historial ('.$timeline->count().')'] as $key => $label)
                        <button
                            type="button"
                            role="tab"
                            id="tab-{{ $key }}"
                            aria-controls="panel-{{ $key }}"
                            x-on:click="tab = '{{ $key }}'"
                            x-bind:aria-selected="tab === '{{ $key }}'"
                            x-bind:class="tab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-ink-muted hover:text-ink'"
                            class="-mb-px min-h-11 cursor-pointer border-b-2 px-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-focus"
                        >{{ $label }}</button>
                    @endforeach
                </div>

                {{-- Comentarios --}}
                <section id="panel-comments" role="tabpanel" aria-labelledby="tab-comments" x-show="tab === 'comments'" class="pt-4">
                    <form wire:submit="addComment" novalidate>
                        <x-ui.field label="Nuevo comentario" for="comment-body" :error="$errors->first('commentBody')">
                            <x-ui.control as="textarea" id="comment-body" rows="3" wire:model="commentBody" maxlength="2000"
                                :invalid="$errors->has('commentBody')" placeholder="Escribe una actualización…"></x-ui.control>
                        </x-ui.field>
                        <div class="mt-2 flex justify-end">
                            <x-ui.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="addComment">Añadir comentario</x-ui.button>
                        </div>
                    </form>

                    <ul class="mt-4 space-y-3">
                        @forelse ($comments as $comment)
                            <li wire:key="comment-{{ $comment['id'] }}" class="rounded-lg border border-line p-3">
                                <p class="flex flex-wrap items-baseline justify-between gap-x-3 text-xs text-ink-muted">
                                    <span class="text-sm font-semibold text-ink">{{ $comment['author'] }}</span>
                                    <time>{{ $comment['date'] }}</time>
                                </p>
                                <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $comment['body'] }}</p>
                            </li>
                        @empty
                            <li class="rounded-lg border border-dashed border-line-strong p-4 text-center text-sm text-ink-muted">
                                Todavía no hay comentarios. El primero cuenta como primera respuesta del SLA.
                            </li>
                        @endforelse
                    </ul>
                </section>

                {{-- Historial de trazabilidad --}}
                <section id="panel-history" role="tabpanel" aria-labelledby="tab-history" x-show="tab === 'history'" x-cloak class="pt-4">
                    <ol class="space-y-4 border-l-2 border-line pl-4">
                        @foreach ($timeline as $entry)
                            <li wire:key="log-{{ $entry->id }}" class="relative">
                                <span class="absolute -left-[1.4rem] top-1.5 size-2.5 rounded-full bg-ink-subtle ring-4 ring-surface" aria-hidden="true"></span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.badge :tone="$entry->tone">{{ $entry->label }}</x-ui.badge>
                                    <time class="text-xs text-ink-muted">{{ $entry->date }}</time>
                                </div>
                                <p class="mt-1 break-words text-sm text-ink">{{ $entry->summary }}</p>
                                @if ($entry->note)
                                    <p class="mt-0.5 text-sm text-ink-muted">{{ $entry->note }}</p>
                                @endif
                                <p class="mt-0.5 text-xs text-ink-muted">
                                    por {{ $entry->author }}@if ($entry->isSystem) · proceso automático @endif
                                </p>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>
        </x-ui.modal>
    @endif
</div>
