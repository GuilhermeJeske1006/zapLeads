@php
    $timezone = $empresa->timezone ?: config('app.timezone');
    $steps = [
        'perfil'     => __('messages.prospecting_step_profile'),
        'resultados' => __('messages.prospecting_step_results'),
        'abordagens' => __('messages.prospecting_step_review'),
        'acompanhar' => __('messages.prospecting_step_follow'),
    ];
    $mapConfig = [
        'token'   => (string) config('services.mapbox.token'),
        'style'   => (string) config('services.mapbox.style', 'mapbox/streets-v12'),
        'company' => $empresa->latitude && $empresa->longitude ? ['lat' => (float) $empresa->latitude, 'lng' => (float) $empresa->longitude] : null,
        'labels'  => ['company' => __('messages.your_company')],
    ];
@endphp

<div class="space-y-5" @if ($passo === 'resultados' && $search?->isActive()) wire:poll.3s="atualizar" @endif>

    {{-- Passos + buscas salvas --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="{{ __('messages.prospecting_steps') }}" class="min-w-0">
            <ol class="flex flex-wrap items-center gap-1 text-sm">
                @foreach ($steps as $key => $label)
                    @php
                        $current = $passo === $key;
                        $disabled = $key === 'resultados' && !$search;
                    @endphp
                    <li class="flex items-center gap-1">
                        @if (!$loop->first)
                            <span class="text-gray-600 px-0.5" aria-hidden="true">›</span>
                        @endif
                        <button type="button" wire:click="irPara('{{ $key }}')" @disabled($disabled)
                                @if ($current) aria-current="step" @endif
                                class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400
                                       {{ $current ? 'bg-emerald-600/20 text-emerald-200' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}">
                            <span class="w-5 h-5 rounded-full text-xs flex items-center justify-center {{ $current ? 'bg-emerald-500 text-gray-950 font-semibold' : 'bg-gray-800 text-gray-300' }}" aria-hidden="true">{{ $loop->iteration }}</span>
                            {{ $label }}
                            @if ($key === 'abordagens' && $pendingDrafts > 0)
                                <span class="px-1.5 rounded-full text-xs bg-amber-500/20 text-amber-200 tabular-nums">
                                    {{ $pendingDrafts }}<span class="sr-only"> {{ __('messages.pending_review') }}</span>
                                </span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>

        <div class="relative" x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false">
            <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="saved-searches"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-gray-200 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                {{ __('messages.saved_searches') }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div id="saved-searches" x-show="open" x-cloak x-transition.opacity
                 class="absolute right-0 z-30 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] max-h-[70vh] overflow-y-auto bg-gray-900 border border-gray-700 rounded-xl shadow-2xl">
                @include('livewire.prospecting.partials.search-history', ['history' => $history, 'timezone' => $timezone, 'compact' => true])
            </div>
        </div>
    </div>

    @if ($placesLimited)
        <p class="px-4 py-3 text-sm rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-200">{{ __('messages.prospecting_limited_mode') }}</p>
    @endif

    {{-- ── Passo 1: perfil ── --}}
    @if ($passo === 'perfil')
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <form wire:submit="buscar" class="xl:col-span-2 bg-gray-900 border border-gray-800 rounded-2xl p-5 sm:p-6 space-y-6" novalidate>
                <header>
                    <h2 class="text-base font-semibold text-white">{{ __('messages.prospecting_profile_title') }}</h2>
                    <p class="text-sm text-gray-400 mt-1">{{ __('messages.prospecting_profile_desc') }}</p>
                </header>

                {{-- Cliente ideal --}}
                <div x-data="{
                        tipos: $wire.entangle('tiposCliente'),
                        novo: '',
                        add() {
                            const v = this.novo.replace(/[,;]+/g, ' ').trim();
                            if (v && !this.tipos.includes(v) && this.tipos.length < 10) this.tipos = [...this.tipos, v];
                            this.novo = '';
                        },
                        remove(i) { this.tipos = this.tipos.filter((_, j) => j !== i); },
                     }">
                    <label for="tipo-cliente-input" class="block text-sm font-medium text-gray-200">{{ __('messages.ideal_customer') }}</label>
                    <p id="tipo-cliente-hint" class="text-xs text-gray-400 mt-0.5">{{ __('messages.ideal_customer_hint') }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5 px-2.5 py-2 bg-gray-800 border rounded-xl focus-within:border-emerald-500
                                {{ $errors->has('tiposCliente') ? 'border-red-500' : 'border-gray-700' }}">
                        <template x-for="(tipo, i) in tipos" :key="tipo">
                            <span class="inline-flex items-center gap-1 pl-2.5 pr-1 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-sm text-emerald-100">
                                <span x-text="tipo"></span>
                                <button type="button" @click="remove(i)" :aria-label="@js(__('messages.remove')) + ' ' + tipo"
                                        class="w-5 h-5 flex items-center justify-center rounded-full hover:bg-emerald-500/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </span>
                        </template>
                        <input id="tipo-cliente-input" type="text" x-model="novo" aria-describedby="tipo-cliente-hint"
                               @keydown.enter.prevent="add()" @keydown.comma.prevent="add()" @blur="add()"
                               @keydown.backspace="if (novo === '' && tipos.length) remove(tipos.length - 1)"
                               placeholder="{{ __('messages.ideal_customer_placeholder') }}"
                               class="flex-1 min-w-40 bg-transparent border-0 p-1 text-sm text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-0">
                    </div>
                    @error('tiposCliente') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                {{-- O que você vende --}}
                <div>
                    <label for="descricao-empresa" class="block text-sm font-medium text-gray-200">{{ __('messages.sales_descricao') }}</label>
                    <p id="descricao-hint" class="text-xs text-gray-400 mt-0.5">
                        {{ __('messages.prospecting_describe_hint') }}
                        <a href="{{ route('empresa.edit') }}#vendas" class="text-emerald-300 hover:text-emerald-200 underline">{{ __('messages.sales_profile_link') }}</a>
                    </p>
                    <textarea id="descricao-empresa" wire:model="descricaoEmpresa" rows="2" maxlength="2000" aria-describedby="descricao-hint"
                              placeholder="{{ __('messages.sales_descricao_placeholder') }}"
                              class="mt-2 w-full px-3.5 py-2.5 bg-gray-800 border rounded-xl text-sm text-gray-100 placeholder-gray-500 resize-y
                                     focus:outline-none focus:border-emerald-500 {{ $errors->has('descricaoEmpresa') ? 'border-red-500' : 'border-gray-700' }}"></textarea>
                    @error('descricaoEmpresa') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                {{-- Onde --}}
                <fieldset class="space-y-3">
                    <legend class="text-sm font-medium text-gray-200">{{ __('messages.where_to_search') }}</legend>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" wire:model.live="onde" value="empresa" @disabled(!$mapConfig['company'])
                               class="mt-1 w-4 h-4 accent-emerald-500 disabled:opacity-40">
                        <span class="min-w-0">
                            <span class="block text-sm text-gray-100">{{ __('messages.where_company_address') }}</span>
                            @if ($mapConfig['company'])
                                <span class="block text-xs text-gray-400 break-words">{{ collect([$empresa->endereco, $empresa->cidade])->filter()->join(' · ') }}</span>
                            @else
                                <span class="block text-xs text-amber-300">{{ __('messages.prospecting_company_address_missing') }}</span>
                            @endif
                            <a href="{{ route('empresa.edit') }}" class="text-xs text-emerald-300 hover:text-emerald-200 underline">{{ __('messages.change_in_my_company') }}</a>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" wire:model.live="onde" value="outro" class="mt-1 w-4 h-4 accent-emerald-500">
                        <span class="block text-sm text-gray-100">{{ __('messages.where_other_place') }}</span>
                    </label>

                    @error('onde') <p class="text-xs text-red-400">{{ $message }}</p> @enderror

                    <div x-show="$wire.onde === 'outro'" x-cloak class="pl-7 space-y-2">
                        <label for="local-busca" class="sr-only">{{ __('messages.where_other_place') }}</label>
                        <input id="local-busca" type="text" wire:model.blur="localBusca" maxlength="150"
                               placeholder="{{ __('messages.where_other_place_placeholder') }}"
                               class="w-full px-3.5 py-2.5 bg-gray-800 border rounded-xl text-sm text-gray-100 placeholder-gray-500
                                      focus:outline-none focus:border-emerald-500 {{ $errors->has('localBusca') ? 'border-red-500' : 'border-gray-700' }}">
                        @error('localBusca') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                        <p class="text-xs text-gray-400">{{ __('messages.where_map_hint') }}</p>
                        <div wire:ignore x-data="prospectingMap(@js($mapConfig + [
                                'mode' => 'picker',
                                'radiusKm' => $raioBuscaKm,
                                'center' => $customLat !== null ? ['lat' => $customLat, 'lng' => $customLng] : $mapConfig['company'],
                                'picked' => $customLat !== null ? ['lat' => $customLat, 'lng' => $customLng] : null,
                             ]))" class="relative z-0" style="isolation: isolate">
                            <div x-ref="canvas" class="h-56 rounded-xl overflow-hidden bg-gray-800" role="application" aria-label="{{ __('messages.where_map_label') }}"></div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="raio" class="text-sm text-gray-200">{{ __('messages.search_radius') }}</label>
                        <input id="raio" type="number" wire:model="raioBuscaKm" min="1" max="50" step="0.5"
                               class="w-24 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-100 focus:outline-none focus:border-emerald-500">
                        <span class="text-sm text-gray-400">km</span>
                    </div>
                    @error('raioBuscaKm') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                </fieldset>

                {{-- Filtros --}}
                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium text-gray-200">{{ __('messages.results_filters') }}</legend>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                        @include('livewire.prospecting.partials.filters')
                    </div>
                </fieldset>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit" wire:loading.attr="disabled" wire:target="buscar"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-60 text-white text-sm font-medium rounded-xl transition-colors
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        {{ __('messages.find_customers') }}
                    </button>
                    <p class="text-xs text-gray-400">{{ __('messages.find_customers_hint') }}</p>
                </div>
            </form>

            <section aria-labelledby="recent-searches" class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden self-start">
                <h2 id="recent-searches" class="px-5 py-4 text-sm font-semibold text-white border-b border-gray-800">{{ __('messages.recent_searches') }}</h2>
                @include('livewire.prospecting.partials.search-history', ['history' => $history->take(5), 'timezone' => $timezone, 'compact' => false])
            </section>
        </div>
    @endif

    {{-- ── Passo 2: resultados ── --}}
    @if ($passo === 'resultados' && $search)
        @include('livewire.prospecting.partials.search-progress')

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
            {{-- Mapa (recolhível no celular) --}}
            <div class="lg:col-span-2" x-data="{ open: window.matchMedia('(min-width: 1024px)').matches }">
                <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="results-map"
                        class="lg:hidden w-full mb-2 inline-flex items-center justify-center gap-2 px-3 py-2 text-sm text-gray-200 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                    <span x-text="open ? @js(__('messages.hide_map')) : @js(__('messages.show_map'))"></span>
                </button>
                <div id="results-map" x-show="open" class="lg:sticky lg:top-0 bg-gray-900 border border-gray-800 rounded-2xl p-3">
                    <div wire:ignore
                         x-data="prospectingMap(@js($mapConfig + [
                            'mode' => 'results',
                            'radiusKm' => $search->radius_km,
                            'center' => ['lat' => $search->latitude, 'lng' => $search->longitude],
                            'markers' => $markers,
                         ]))"
                         x-on:prospecting-map:results.window="render($event.detail)"
                         class="relative z-0" style="isolation: isolate">
                        <div x-ref="canvas" class="h-72 lg:h-[32rem] rounded-xl overflow-hidden bg-gray-800" role="application" aria-label="{{ __('messages.results_map_label') }}"></div>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">{{ __('messages.results_map_hint') }}</p>
                </div>
            </div>

            {{-- Lista --}}
            <section aria-labelledby="results-title" class="lg:col-span-3 bg-gray-900 border border-gray-800 rounded-2xl">
                <div class="px-4 sm:px-5 py-4 border-b border-gray-800 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="results-title" class="text-sm font-semibold text-white">
                            {{ __('messages.results_title') }}
                            <span class="ml-1 text-xs font-normal text-gray-400">{{ __('messages.results_count', ['shown' => $leads->count(), 'total' => $totalLeads]) }}</span>
                        </h2>
                        <button type="button" wire:click="ordenarPorScore"
                                class="text-xs text-gray-300 hover:text-white underline rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                            {{ __('messages.sort_by_score') }}
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        @include('livewire.prospecting.partials.filters', ['live' => true])
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="selecionarTop" @disabled($leads->isEmpty())
                                class="px-3 py-1.5 text-xs text-gray-200 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg disabled:opacity-40
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                            {{ __('messages.select_top', ['count' => \App\Livewire\Prospecting\ProspectingWizard::TOP]) }}
                        </button>
                        @if (count($selecionados) > 0)
                            <button type="button" wire:click="limparSelecao"
                                    class="px-3 py-1.5 text-xs text-gray-300 hover:text-white rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                {{ __('messages.clear_selection') }}
                            </button>
                        @endif
                        <button type="button" wire:click="gerarAbordagens" wire:loading.attr="disabled" wire:target="gerarAbordagens" @disabled(count($selecionados) === 0)
                                class="ml-auto px-3 py-1.5 text-xs font-medium bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg disabled:opacity-40 disabled:cursor-not-allowed
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                            {{ trans_choice('messages.generate_outreach_selected', count($selecionados), ['count' => count($selecionados)]) }}
                        </button>
                    </div>
                </div>

                <ul class="divide-y divide-gray-800/70">
                    @forelse ($leads as $lead)
                        @php
                            $ai = $lead->ai_insights ?? [];
                            $selectable = $lead->status === 'novo' && $lead->telefone_e164 && !$lead->isOptedOut();
                            $waLink = \App\Support\Phone::waMeLink($lead->telefone, $empresa->country);
                            $hint = $ai['gancho'] ?? $ai['match_motivo'] ?? null;
                        @endphp
                        <li wire:key="result-{{ $lead->id }}" class="flex items-start gap-3 px-4 sm:px-5 py-3 hover:bg-gray-800/30">
                            <input type="checkbox" wire:model.live="selecionados" value="{{ $lead->id }}" @disabled(!$selectable)
                                   aria-label="{{ __('messages.select_lead', ['nome' => $lead->nome]) }}"
                                   class="mt-1 w-4 h-4 rounded border-gray-600 bg-gray-800 text-emerald-500 focus:ring-emerald-500 disabled:opacity-30">

                            <x-lead-score :score="$lead->lead_score" class="mt-0.5 shrink-0" />

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <button type="button" wire:click="$dispatch('open-lead-dossier', { id: {{ $lead->id }} })"
                                            class="text-left text-sm font-medium text-white hover:text-emerald-200 break-words rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                        {{ $lead->nome }}
                                    </button>
                                    @if ($lead->status !== 'novo')
                                        <x-lead-status :status="$lead->status" />
                                    @endif
                                </div>
                                @if ($hint)
                                    <p class="text-xs text-gray-300 mt-0.5 line-clamp-2">{{ $hint }}</p>
                                @endif
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-gray-400">
                                    <x-contact-badge :status="$lead->enrichment_status" :confidence="$lead->contact_confidence" :origin="$lead->primaryContact?->origem" />
                                    @if (!empty($ai['rating']))
                                        <span>★ {{ number_format((float) $ai['rating'], 1, ',', '') }} <span class="text-gray-400">({{ (int) ($ai['user_ratings_total'] ?? 0) }})</span></span>
                                    @endif
                                    @if ($lead->distancia_km !== null)
                                        <span>{{ number_format((float) $lead->distancia_km, 1, ',', '') }} km</span>
                                    @endif
                                    @if (!$lead->website)
                                        <span>{{ __('messages.signal_no_site') }}</span>
                                    @endif
                                </div>
                            </div>

                            @if ($waLink)
                                <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                                   aria-label="{{ __('messages.open_whatsapp_with', ['numero' => $lead->nome]) }}"
                                   class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[#25D366]/10 hover:bg-[#25D366]/20 text-[#25D366] border border-[#25D366]/30
                                          focus:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366]">
                                    <x-icons.whatsapp class="w-4 h-4" />
                                </a>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-gray-400">
                            @if (in_array($search->status, ['queued', 'running'], true))
                                {{ __('messages.results_loading') }}
                            @elseif ($totalLeads > 0)
                                {{ __('messages.results_filtered_out') }}
                            @elseif ($search->status === 'done')
                                {{ __('messages.results_none') }}
                            @endif
                        </li>
                    @endforelse
                </ul>
            </section>
        </div>
    @endif

    {{-- ── Passo 3: revisar abordagens ── --}}
    @if ($passo === 'abordagens')
        <livewire:leads.outreach-queue :empresa="$empresa" wire:key="prospecting-queue" />
    @endif

    {{-- ── Passo 4: acompanhar ── --}}
    @if ($passo === 'acompanhar')
        <livewire:leads.pipeline-board :empresa="$empresa" wire:key="prospecting-pipeline" />
    @endif
</div>
