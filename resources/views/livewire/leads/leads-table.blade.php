<div id="leads-table" class="space-y-4">

    @if ($pendingDrafts > 0)
        <a href="{{ route('prospeccao.index', ['passo' => 'abordagens']) }}"
           class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-sm text-amber-100 hover:bg-amber-500/15
                  focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
            <span>{{ trans_choice('messages.drafts_waiting_review', $pendingDrafts, ['count' => $pendingDrafts]) }}</span>
            <span class="font-medium underline">{{ __('messages.review_now') }}</span>
        </a>
    @endif

    {{-- Ações --}}
    <div class="flex flex-wrap items-center justify-end gap-2">
        <a href="{{ route('prospeccao.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors
                  focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            {{ __('messages.find_customers') }}
        </a>

        <button type="button" wire:click="recalcularScores" wire:confirm="{{ __('messages.rescore_confirm') }}" title="{{ __('messages.rescore_hint') }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium bg-violet-500/10 hover:bg-violet-500/20 text-violet-200 border border-violet-500/30 rounded-lg transition-colors
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-300">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            {{ __('messages.rescore_leads') }}
        </button>

        <button type="button" wire:click="abrirAddModal"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium bg-green-600/20 hover:bg-green-600/30 text-green-300 border border-green-600/40 rounded-lg transition-colors
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-green-300">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('messages.add_lead') }}
        </button>

        <button type="button" wire:click="deleteAllLeads" wire:confirm="{{ __('messages.delete_all_leads_confirm') }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium bg-red-500/10 hover:bg-red-500/20 text-red-300 border border-red-500/30 rounded-lg transition-colors
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            {{ __('messages.delete_all') }}
        </button>
    </div>

    {{-- Filtros --}}
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-40">
                <label for="leads-search" class="block text-xs text-gray-400 mb-1">{{ __('messages.search_by_name') }}</label>
                <input id="leads-search" wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('messages.search_by_name_placeholder') }}"
                       class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-green-500">
            </div>

            <div>
                <label for="leads-status" class="block text-xs text-gray-400 mb-1">{{ __('messages.funnel_stage') }}</label>
                <select id="leads-status" wire:model.live="filterStatus"
                        class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-green-500">
                    <option value="">{{ __('messages.all') }}</option>
                    @foreach (\App\Models\Lead::STATUSES as $val => $meta)
                        <option value="{{ $val }}">{{ \App\Models\Lead::statusLabel($val) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="leads-origem" class="block text-xs text-gray-400 mb-1">{{ __('messages.lead_origin') }}</label>
                <select id="leads-origem" wire:model.live="filterSource"
                        class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-green-500">
                    <option value="">{{ __('messages.all') }}</option>
                    @foreach (array_keys(\App\Livewire\Leads\LeadsTable::ORIGENS) as $origem)
                        <option value="{{ $origem }}">{{ __('messages.lead_source_' . $origem) }}</option>
                    @endforeach
                </select>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-200 cursor-pointer pb-2">
                <input wire:model.live="filterNearby" type="checkbox" class="w-4 h-4 accent-green-500">
                {{ __('messages.nearby') }}
            </label>

            <label class="flex items-center gap-2 text-sm text-gray-200 cursor-pointer pb-2">
                <input wire:model.live="geoFilterEnabled" type="checkbox" class="w-4 h-4 accent-amber-500">
                {{ __('messages.filter_by_place') }}
            </label>

            @if ($hasFilters)
                <button type="button" wire:click="limparFiltros"
                        class="text-xs text-gray-300 hover:text-white underline pb-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-green-400">
                    {{ __('messages.clear_filters') }}
                </button>
            @endif
        </div>

        {{-- Filtro por local --}}
        <div class="{{ $geoFilterEnabled ? '' : 'hidden' }} mt-4 pt-4 border-t border-gray-800" data-leads-geo-panel>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-xs text-gray-400">{{ __('messages.geo_center') }}</span>
                    @if ($geoLabel)
                        <span class="text-xs text-amber-300 truncate max-w-[520px]" title="{{ $geoLabel }}">{{ $geoLabel }}</span>
                    @else
                        <span class="text-xs text-gray-400">{{ __('messages.geo_center_pick') }}</span>
                    @endif
                </div>

                <div class="ml-auto flex items-end gap-2">
                    <div>
                        <label for="leads-geo-radius" class="block text-xs text-gray-400 mb-1">{{ __('messages.search_radius') }} (km)</label>
                        <input id="leads-geo-radius" wire:model.live="geoRadiusKm" type="number" min="1" max="200" step="0.5" data-leads-geo-radius
                               class="w-28 px-3 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-amber-500">
                    </div>

                    <button type="button" data-leads-geo-locate
                            class="mb-0.5 px-3 py-1.5 text-xs font-medium bg-amber-500/10 hover:bg-amber-500/20 text-amber-200 border border-amber-500/30 rounded-lg transition-colors
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                        {{ __('messages.use_my_location') }}
                    </button>

                    <button type="button" wire:click="resetGeoFilter"
                            class="mb-0.5 px-3 py-1.5 text-xs font-medium bg-gray-800 hover:bg-gray-700 text-gray-200 border border-gray-700 rounded-lg transition-colors
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300">
                        {{ __('messages.reset') }}
                    </button>
                </div>
            </div>

            <p class="mt-2 text-xs text-gray-400">{{ __('messages.geo_map_hint') }}</p>

            <div class="mt-3" style="isolation: isolate; position: relative; z-index: 0;" wire:ignore>
                <div id="leads-geo-map" data-leads-geo-map
                     data-mapbox-token="{{ (string) config('services.mapbox.token') }}"
                     data-mapbox-style="{{ (string) config('services.mapbox.style', 'mapbox/streets-v12') }}"
                     data-center-lat="{{ (string) ($empresa->latitude ?? '') }}"
                     data-center-lng="{{ (string) ($empresa->longitude ?? '') }}"
                     class="h-56 rounded-xl overflow-hidden bg-gray-800"></div>
            </div>
        </div>
    </div>

    {{-- Lista --}}
    <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">

        {{-- Celular: cards --}}
        <ul class="md:hidden divide-y divide-gray-800">
            @forelse ($leads as $lead)
                @php $waLink = \App\Support\Phone::waMeLink($lead->telefone, $empresa->country); @endphp
                <li wire:key="lead-card-{{ $lead->id }}" class="p-4 space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <button type="button" wire:click="$dispatch('open-lead-dossier', { id: {{ $lead->id }} })"
                                class="min-w-0 text-left text-sm font-medium text-white break-words rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                            {{ $lead->nome }}
                        </button>
                        <x-lead-score :score="$lead->lead_score" class="shrink-0" />
                    </div>
                    <p class="text-xs text-gray-400">
                        {{ collect([$lead->cidade, $lead->distancia_km ? number_format($lead->distancia_km, 1, ',', '') . ' km' : null])->filter()->join(' · ') }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-lead-status :status="$lead->status" />
                        <x-contact-badge :status="$lead->enrichment_status" :confidence="$lead->contact_confidence" :origin="$lead->primaryContact?->origem" />
                    </div>
                    <div class="flex flex-wrap gap-2 pt-1">
                        @if ($waLink)
                            <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg bg-[#25D366]/10 text-[#25D366] border border-[#25D366]/30
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366]">
                                <x-icons.whatsapp class="w-3.5 h-3.5" /> {{ __('messages.whatsapp') }}
                            </a>
                            <button type="button" wire:click="gerarAbordagem({{ $lead->id }})"
                                    class="px-3 py-1.5 text-xs rounded-lg bg-emerald-500/10 text-emerald-200 border border-emerald-500/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                {{ __('messages.generate_outreach') }}
                            </button>
                        @endif
                        <button type="button" wire:click="$dispatch('open-lead-dossier', { id: {{ $lead->id }} })"
                                class="px-3 py-1.5 text-xs rounded-lg bg-gray-800 text-gray-200 border border-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300">
                            {{ __('messages.view_dossier') }}
                        </button>
                    </div>
                </li>
            @empty
                <li class="px-4 py-12 text-center text-sm text-gray-400">
                    {{ $hasFilters ? __('messages.no_lead_found') : __('messages.no_leads_yet_cta') }}
                </li>
            @endforelse
        </ul>

        {{-- Desktop: tabela --}}
        <table class="hidden md:table w-full text-sm table-fixed">
            <colgroup>
                <col class="w-[34%]">
                <col class="w-[20%]">
                <col class="w-[14%]">
                <col class="w-[14%]">
                <col class="w-[18%]">
            </colgroup>
            <thead>
                <tr class="border-b border-gray-800 text-xs text-gray-300">
                    <th scope="col" class="px-4 py-3 text-left font-medium">{{ __('messages.lead') }}</th>
                    <th scope="col" class="px-3 py-3 text-left font-medium">{{ __('messages.phone') }}</th>
                    <th scope="col" class="px-3 py-3 text-left font-medium">{{ __('messages.location') }}</th>
                    <th scope="col" class="px-3 py-3 text-left font-medium">{{ __('messages.funnel_stage') }}</th>
                    <th scope="col" class="px-3 py-3 text-left font-medium">{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/50">
                @forelse ($leads as $lead)
                    @php
                        $waLink = \App\Support\Phone::waMeLink($lead->telefone, $empresa->country);
                        $website = $lead->website && preg_match('#^https?://#i', $lead->website) ? $lead->website : null;
                    @endphp
                    <tr wire:key="lead-row-{{ $lead->id }}" class="hover:bg-gray-800/30 transition-colors">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <x-lead-score :score="$lead->lead_score" class="shrink-0" />
                                <div class="min-w-0">
                                    <button type="button" wire:click="$dispatch('open-lead-dossier', { id: {{ $lead->id }} })" title="{{ $lead->nome }}"
                                            class="block max-w-full text-left text-white font-medium text-sm truncate hover:text-emerald-200 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                        {{ $lead->nome }}
                                    </button>
                                    @if ($lead->endereco)
                                        <p class="text-gray-400 text-xs truncate" title="{{ $lead->endereco }}">{{ $lead->endereco }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td class="px-3 py-3">
                            <span class="text-gray-300 font-mono text-xs block truncate">{{ \App\Support\Phone::display($lead->telefone, $empresa->country) ?? '—' }}</span>
                            <x-contact-badge :status="$lead->enrichment_status" :confidence="$lead->contact_confidence" :origin="$lead->primaryContact?->origem" />
                            @if ($website)
                                <a href="{{ $website }}" target="_blank" rel="noopener noreferrer nofollow" title="{{ $website }}"
                                   class="block text-xs text-blue-300 hover:text-blue-200 mt-0.5 truncate max-w-full">{{ parse_url($website, PHP_URL_HOST) ?: $website }}</a>
                            @endif
                        </td>

                        <td class="px-3 py-3">
                            <p class="text-gray-300 text-xs truncate">{{ $lead->cidade ?? '—' }}</p>
                            @if ($lead->distancia_km)
                                <p class="text-xs {{ $lead->is_nearby ? 'text-green-300' : 'text-gray-400' }}">{{ number_format($lead->distancia_km, 1, ',', '') }} km</p>
                            @endif
                        </td>

                        <td class="px-3 py-3">
                            <label for="lead-status-{{ $lead->id }}" class="sr-only">{{ __('messages.funnel_stage_of', ['nome' => $lead->nome]) }}</label>
                            <select id="lead-status-{{ $lead->id }}" wire:change="alterarStatus({{ $lead->id }}, $event.target.value)"
                                    class="w-full text-xs px-1.5 py-1 rounded-md border bg-transparent cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 {{ \App\Models\Lead::statusClasses($lead->status) }}">
                                @foreach (\App\Models\Lead::STATUSES as $val => $meta)
                                    <option value="{{ $val }}" @selected($lead->status === $val) class="bg-gray-900 text-gray-200">{{ \App\Models\Lead::statusLabel($val) }}</option>
                                @endforeach
                            </select>
                        </td>

                        <td class="px-3 py-3">
                            <div class="flex items-center gap-1">
                                @if ($waLink)
                                    <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                                       title="{{ __('messages.open_in_my_whatsapp') }}" aria-label="{{ __('messages.open_whatsapp_with', ['numero' => $lead->nome]) }}"
                                       class="inline-flex items-center justify-center w-8 h-8 bg-[#25D366]/10 hover:bg-[#25D366]/20 text-[#25D366] border border-[#25D366]/30 rounded-lg transition-colors
                                              focus:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366]">
                                        <x-icons.whatsapp class="w-3.5 h-3.5" />
                                    </a>

                                    <button type="button" wire:click="gerarAbordagem({{ $lead->id }})"
                                            wire:loading.attr="disabled" wire:target="gerarAbordagem({{ $lead->id }})"
                                            title="{{ __('messages.generate_outreach') }}" aria-label="{{ __('messages.generate_outreach_for', ['nome' => $lead->nome]) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 rounded-lg transition-colors disabled:opacity-50
                                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </button>
                                @endif

                                <button type="button" wire:click="$dispatch('open-lead-dossier', { id: {{ $lead->id }} })"
                                        title="{{ __('messages.view_dossier') }}" aria-label="{{ __('messages.view_dossier_of', ['nome' => $lead->nome]) }}"
                                        class="inline-flex items-center justify-center w-8 h-8 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded-lg transition-colors
                                               focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>

                                <button type="button" wire:click="deleteLead({{ $lead->id }})" wire:confirm="{{ __('messages.delete_lead_confirm', ['nome' => $lead->nome]) }}"
                                        title="{{ __('messages.delete') }}" aria-label="{{ __('messages.delete_lead', ['nome' => $lead->nome]) }}"
                                        class="inline-flex items-center justify-center w-8 h-8 bg-red-500/10 hover:bg-red-500/20 text-red-300 border border-red-500/30 rounded-lg transition-colors
                                               focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-400 text-sm">
                            {{ $hasFilters ? __('messages.no_lead_found') : __('messages.no_leads_yet_cta') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($leads->hasPages())
            <div class="px-4 py-4 border-t border-gray-800">
                {{ $leads->links() }}
            </div>
        @endif
    </div>

    {{-- Escolher canal --}}
    @teleport('body')
    <div x-data x-show="$wire.showSelectChannelModal" x-cloak class="fixed inset-0 flex items-center justify-center p-4" style="z-index: 9999;"
         role="dialog" aria-modal="true" aria-labelledby="select-channel-title" @keydown.escape.window="$wire.fecharSelectChannelModal()">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" wire:click="fecharSelectChannelModal" aria-hidden="true"></div>
        <div class="relative w-full max-w-md bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                <h3 id="select-channel-title" class="text-base font-semibold text-white flex items-center gap-2">
                    <x-icons.whatsapp class="w-5 h-5 text-emerald-400" />
                    {{ __('messages.choose_channel_title') }}
                </h3>
                <button type="button" wire:click="fecharSelectChannelModal" aria-label="{{ __('messages.close') }}"
                        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <fieldset class="px-6 py-5 space-y-3">
                <legend class="text-sm text-gray-300">{{ __('messages.choose_outreach_channel') }}</legend>
                <div class="space-y-2">
                    @foreach ($channels as $ch)
                        <label class="flex items-center gap-3 p-3 bg-gray-800 hover:bg-gray-700 border rounded-xl cursor-pointer transition-colors
                                      {{ $selectedChannelId === $ch['id'] ? 'border-emerald-500/60 bg-emerald-500/5' : 'border-gray-700' }}">
                            <input type="radio" wire:model.live="selectedChannelId" value="{{ $ch['id'] }}" class="w-4 h-4 accent-emerald-500">
                            <span>
                                <span class="block text-sm font-medium text-white">{{ $ch['nome'] }}</span>
                                <span class="block text-xs text-gray-400 font-mono">{{ $ch['numero'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-800">
                <button type="button" wire:click="fecharSelectChannelModal"
                        class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-sm rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300">
                    {{ __('messages.cancel') }}
                </button>
                <button type="button" wire:click="confirmarCanal"
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                    {{ __('messages.generate_outreach') }}
                </button>
            </div>
        </div>
    </div>
    @endteleport

    {{-- Adicionar lead --}}
    @teleport('body')
    <div x-data x-show="$wire.showAddModal" x-cloak class="fixed inset-0 flex items-center justify-center p-4" style="z-index: 9999;"
         role="dialog" aria-modal="true" aria-labelledby="add-lead-title" @keydown.escape.window="$wire.fecharAddModal()">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" wire:click="fecharAddModal" aria-hidden="true"></div>

        <div class="relative w-full max-w-lg bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                <h3 id="add-lead-title" class="text-base font-semibold text-white">{{ __('messages.add_lead') }}</h3>
                <button type="button" wire:click="fecharAddModal" aria-label="{{ __('messages.close') }}"
                        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="salvarLead" class="px-6 py-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="add-nome" class="block text-xs text-gray-300 mb-1">{{ __('messages.name') }} <span class="text-red-400" aria-hidden="true">*</span></label>
                        <input id="add-nome" wire:model="addNome" type="text" required placeholder="{{ __('messages.add_lead_name_placeholder') }}"
                               class="w-full px-3 py-2 bg-gray-800 border text-white text-sm rounded-lg placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-green-500 {{ $errors->has('addNome') ? 'border-red-500' : 'border-gray-700' }}">
                        @error('addNome') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="add-telefone" class="block text-xs text-gray-300 mb-1">{{ __('messages.phone') }} <span class="text-red-400" aria-hidden="true">*</span></label>
                        <input id="add-telefone" wire:model="addTelefone" type="tel" required placeholder="{{ __('messages.add_lead_phone_placeholder') }}"
                               class="w-full px-3 py-2 bg-gray-800 border text-white text-sm rounded-lg placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-green-500 {{ $errors->has('addTelefone') ? 'border-red-500' : 'border-gray-700' }}">
                        @error('addTelefone') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="add-status" class="block text-xs text-gray-300 mb-1">{{ __('messages.funnel_stage') }}</label>
                        <select id="add-status" wire:model="addStatus"
                                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 text-gray-200 text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            @foreach (\App\Models\Lead::STATUSES as $val => $meta)
                                <option value="{{ $val }}">{{ \App\Models\Lead::statusLabel($val) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="add-cidade" class="block text-xs text-gray-300 mb-1">{{ __('messages.city') }}</label>
                        <input id="add-cidade" wire:model="addCidade" type="text"
                               class="w-full px-3 py-2 bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>

                    <div>
                        <label for="add-endereco" class="block text-xs text-gray-300 mb-1">{{ __('messages.address') }}</label>
                        <input id="add-endereco" wire:model="addEndereco" type="text"
                               class="w-full px-3 py-2 bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="add-website" class="block text-xs text-gray-300 mb-1">{{ __('messages.website') }}</label>
                        <input id="add-website" wire:model="addWebsite" type="url" placeholder="https://"
                               class="w-full px-3 py-2 bg-gray-800 border text-white text-sm rounded-lg placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-green-500 {{ $errors->has('addWebsite') ? 'border-red-500' : 'border-gray-700' }}">
                        @error('addWebsite') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="fecharAddModal"
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-sm rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300">
                        {{ __('messages.cancel') }}
                    </button>
                    <button type="submit"
                            class="px-5 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-green-300">
                        {{ __('messages.save_lead') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endteleport
</div>
