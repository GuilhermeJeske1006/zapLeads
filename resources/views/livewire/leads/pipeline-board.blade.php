<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-48">
            <label for="pipeline-busca" class="block text-xs text-gray-400 mb-1">{{ __('messages.search_by_name') }}</label>
            <input id="pipeline-busca" type="search" wire:model.live.debounce.300ms="busca" placeholder="{{ __('messages.search_by_name_placeholder') }}"
                   class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 placeholder-gray-500
                          focus:outline-none focus:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/40">
        </div>
        <div>
            <label for="pipeline-origem" class="block text-xs text-gray-400 mb-1">{{ __('messages.lead_origin') }}</label>
            <select id="pipeline-origem" wire:model.live="origem"
                    class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                <option value="">{{ __('messages.all') }}</option>
                <option value="prospeccao">{{ __('messages.lead_origin_prospecting') }}</option>
                <option value="catalogo">{{ __('messages.lead_origin_catalog') }}</option>
            </select>
        </div>
        <p class="w-full text-xs text-gray-400">{{ __('messages.pipeline_hint') }}</p>
    </div>

    <div class="flex gap-3 overflow-x-auto pb-3 snap-x snap-mandatory" role="list" aria-label="{{ __('messages.pipeline_title') }}">
        @foreach ($columns as $status => $leads)
            @php $total = (int) ($counts[$status] ?? 0); @endphp
            <section role="listitem" aria-labelledby="pipeline-col-{{ $status }}"
                     class="snap-start shrink-0 w-[85vw] sm:w-72 flex flex-col bg-gray-900 border border-gray-800 rounded-2xl">
                <header class="flex items-center justify-between gap-2 px-3 py-2.5 border-b border-gray-800">
                    <h3 id="pipeline-col-{{ $status }}" class="flex items-center gap-2 text-sm font-medium text-white">
                        <x-lead-status :status="$status" />
                    </h3>
                    <span class="text-xs tabular-nums text-gray-400">{{ $total }}</span>
                </header>

                <ul class="flex-1 min-h-32 max-h-[calc(100vh-17rem)] overflow-y-auto p-2 space-y-2"
                    wire:sort="mover" wire:sort:group="pipeline" wire:sort:group-id="{{ $status }}">
                    @if ($leads->isEmpty())
                        <li wire:key="pipeline-empty-{{ $status }}" wire:sort:ignore class="px-2 py-6 text-xs text-center text-gray-400">{{ __('messages.pipeline_column_empty') }}</li>
                    @endif
                    @foreach ($leads as $lead)
                        <li wire:key="pipeline-{{ $lead->id }}" wire:sort:item="{{ $lead->id }}"
                            class="bg-gray-800/70 border border-gray-700/60 rounded-xl p-3 cursor-grab active:cursor-grabbing">
                            <div class="flex items-start justify-between gap-2">
                                <button type="button" wire:click="$dispatch('open-lead-dossier', { id: {{ $lead->id }} })"
                                        class="min-w-0 text-left text-sm font-medium text-white hover:text-emerald-200 break-words rounded
                                               focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                    {{ $lead->nome }}
                                </button>
                                <x-lead-score :score="$lead->lead_score" class="shrink-0" />
                            </div>
                            @if ($lead->cidade)
                                <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $lead->cidade }}</p>
                            @endif
                            <x-contact-badge :status="$lead->enrichment_status" :confidence="$lead->contact_confidence" :origin="$lead->primaryContact?->origem" />
                            @if ($lead->isOptedOut())
                                <p class="mt-1 text-xs text-red-300">{{ __('messages.opted_out_badge') }}</p>
                            @endif

                            <div class="mt-2" wire:sort:ignore>
                                <label for="pipeline-move-{{ $lead->id }}" class="sr-only">{{ __('messages.move_lead_to', ['nome' => $lead->nome]) }}</label>
                                <select id="pipeline-move-{{ $lead->id }}" wire:change="moverPara({{ $lead->id }}, $event.target.value)"
                                        class="w-full text-xs px-2 py-1 bg-gray-900 border border-gray-700 rounded-md text-gray-300
                                               focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                    @foreach (\App\Models\Lead::STATUSES as $value => $meta)
                                        <option value="{{ $value }}" @selected($value === $status)>
                                            {{ $value === $status ? __('messages.move_to_placeholder') : \App\Models\Lead::statusLabel($value) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($total > $leads->count())
                    <a href="{{ route('leads.index', ['status' => $status]) }}"
                       class="block px-3 py-2 text-xs text-center text-emerald-300 hover:text-emerald-200 border-t border-gray-800 rounded-b-2xl
                              focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                        {{ __('messages.pipeline_see_all', ['count' => $total]) }}
                    </a>
                @endif
            </section>
        @endforeach
    </div>
</div>
