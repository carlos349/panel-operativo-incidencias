<div>
    @if ($card)
        <x-ui.modal variant="panel" labelledby="detail-title">
            <x-slot:title>
                <p class="font-mono text-xs font-medium text-slate-500">{{ $card->reference }}</p>
                @unless ($editing)
                    <h2 class="mt-0.5 text-lg font-semibold leading-snug text-slate-900">{{ $card->title }}</h2>
                @else
                    <h2 class="mt-0.5 text-lg font-semibold text-slate-900">Editando incidencia</h2>
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
                        <h3 class="text-sm font-semibold text-slate-700">Descripción</h3>
                        <x-ui.button variant="ghost" size="sm" wire:click="startEditing">
                            <x-ui.icon name="pencil" class="size-4!" />
                            Editar título y descripción
                        </x-ui.button>
                    </div>
                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $card->description }}</p>
                </div>
            @endif

            {{-- Datos --}}
            <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
                @foreach ([
                    'Responsable' => $card->assignee,
                    'Solicitante' => $card->requester,
                    'Categoría' => $card->category ?? 'Sin categoría',
                    'Creada' => $card->createdAt.' ('.$card->createdAgo.')',
                    'SLA de resolución' => $card->slaDetail ?? 'Sin plazo calculado',
                    'Primera respuesta' => $firstResponse['label'],
                ] as $term => $value)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $term }}</dt>
                        <dd class="mt-0.5 text-slate-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            {{-- Cambio de estado --}}
            <div class="mt-5">
                <h3 class="text-sm font-semibold text-slate-700">Cambiar estado</h3>
                <div class="mt-2 flex flex-wrap gap-2">
                    @forelse ($card->transitions as $transition)
                        <x-ui.button variant="secondary" size="sm" wire:key="transition-{{ $transition['id'] }}" wire:click="changeStatus({{ $transition['id'] }})">
                            {{ $card->isResolved ? 'Reabrir (pasar a '.$transition['name'].')' : 'Pasar a '.$transition['name'] }}
                        </x-ui.button>
                    @empty
                        <p class="text-sm text-slate-500">No hay transiciones disponibles.</p>
                    @endforelse
                </div>
            </div>

            {{-- Pestañas internas --}}
            <div class="mt-6" x-data="{ tab: 'comments' }">
                <div class="flex gap-1 border-b border-slate-200" role="tablist" aria-label="Actividad de la incidencia">
                    @foreach (['comments' => 'Comentarios ('.$comments->count().')', 'history' => 'Historial ('.$timeline->count().')'] as $key => $label)
                        <button
                            type="button"
                            role="tab"
                            id="tab-{{ $key }}"
                            aria-controls="panel-{{ $key }}"
                            x-on:click="tab = '{{ $key }}'"
                            x-bind:aria-selected="tab === '{{ $key }}'"
                            x-bind:class="tab === '{{ $key }}' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-600 hover:text-slate-900'"
                            class="-mb-px min-h-11 cursor-pointer border-b-2 px-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-indigo-600"
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
                            <li wire:key="comment-{{ $comment['id'] }}" class="rounded-lg border border-slate-200 p-3">
                                <p class="flex flex-wrap items-baseline justify-between gap-x-3 text-xs text-slate-500">
                                    <span class="text-sm font-semibold text-slate-900">{{ $comment['author'] }}</span>
                                    <time>{{ $comment['date'] }}</time>
                                </p>
                                <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $comment['body'] }}</p>
                            </li>
                        @empty
                            <li class="rounded-lg border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500">
                                Todavía no hay comentarios. El primero cuenta como primera respuesta del SLA.
                            </li>
                        @endforelse
                    </ul>
                </section>

                {{-- Historial de trazabilidad --}}
                <section id="panel-history" role="tabpanel" aria-labelledby="tab-history" x-show="tab === 'history'" x-cloak class="pt-4">
                    <ol class="space-y-4 border-l-2 border-slate-200 pl-4">
                        @foreach ($timeline as $entry)
                            <li wire:key="log-{{ $entry->id }}" class="relative">
                                <span class="absolute -left-[1.4rem] top-1.5 size-2.5 rounded-full bg-slate-400 ring-4 ring-white" aria-hidden="true"></span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.badge :tone="$entry->tone">{{ $entry->label }}</x-ui.badge>
                                    <time class="text-xs text-slate-500">{{ $entry->date }}</time>
                                </div>
                                <p class="mt-1 break-words text-sm text-slate-800">{{ $entry->summary }}</p>
                                @if ($entry->note)
                                    <p class="mt-0.5 text-sm text-slate-600">{{ $entry->note }}</p>
                                @endif
                                <p class="mt-0.5 text-xs text-slate-500">
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
