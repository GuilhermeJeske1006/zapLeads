<div>
@if ($lead)
    @php
        $ai = $lead->ai_insights ?? [];
        $dossie = $lead->dossie ?? [];
        $country = $empresa->country ?? 'BR';
        $waLink = \App\Support\Phone::waMeLink($lead->telefone, $country);
        $mapsLink = (isset($dossie['google_maps']) && str_starts_with($dossie['google_maps'], 'https://') ? $dossie['google_maps'] : null)
            ?? (($lead->latitude && $lead->longitude) ? 'https://www.google.com/maps?q=' . $lead->latitude . ',' . $lead->longitude : null);
        $anos = $lead->data_abertura ? (int) $lead->data_abertura->diffInYears(now()) : null;
        $reviews = array_slice($dossie['reviews'] ?? [], 0, 3);
        // Links typed by users or read from the web: only http(s) becomes a link.
        $httpUrl = fn (?string $url) => $url && preg_match('#^https?://#i', $url) ? $url : null;
        $website = $httpUrl($lead->website);
        $decisorFonte = $httpUrl($dossie['decisor_fonte'] ?? null);
    @endphp

    <div class="fixed inset-0 z-40 flex justify-end"
         role="dialog" aria-modal="true" aria-labelledby="dossier-title"
         x-data x-trap.noscroll="true" @keydown.escape.window="$wire.fechar()"
         @if ($busy) wire:poll.3s @endif>

        <div class="absolute inset-0 bg-black/60" wire:click="fechar" aria-hidden="true"></div>

        <aside class="relative flex flex-col w-full sm:max-w-lg h-full bg-gray-900 border-l border-gray-800 shadow-2xl">

            {{-- Cabeçalho --}}
            <header class="flex items-start gap-3 px-5 py-4 border-b border-gray-800">
                <div class="w-10 h-10 rounded-full bg-linear-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-sm font-bold text-white shrink-0" aria-hidden="true">
                    {{ mb_strtoupper(mb_substr($lead->nome, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <h2 id="dossier-title" class="text-base font-semibold text-white break-words">{{ $lead->nome }}</h2>
                    <p class="text-xs text-gray-400 break-words">
                        {{ collect([$dossie['segmento'] ?? null, $lead->endereco ?: $lead->cidade])->filter()->join(' · ') }}
                    </p>
                </div>
                <button type="button" wire:click="fechar" aria-label="{{ __('messages.close') }}"
                        class="w-9 h-9 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </header>

            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                {{-- Status --}}
                <div class="flex flex-wrap items-center gap-3">
                    <label for="dossier-status" class="text-xs text-gray-400">{{ __('messages.funnel_stage') }}</label>
                    <select id="dossier-status" wire:change="alterarStatus($event.target.value)"
                            class="text-sm px-2.5 py-1.5 rounded-lg border bg-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 {{ \App\Models\Lead::statusClasses($lead->status) }}">
                        @foreach (\App\Models\Lead::STATUSES as $value => $meta)
                            <option value="{{ $value }}" @selected($lead->status === $value) class="bg-gray-900 text-gray-200">{{ \App\Models\Lead::statusLabel($value) }}</option>
                        @endforeach
                    </select>
                    @if ($lead->isOptedOut())
                        <span class="text-xs px-2 py-0.5 rounded-full bg-red-500/15 text-red-300 border border-red-500/30">{{ __('messages.opted_out_badge') }}</span>
                    @endif
                </div>

                <x-score-breakdown :lead="$lead->toArray()" />

                {{-- Decisor --}}
                <section aria-labelledby="dossier-decisor" class="bg-gray-800/60 rounded-xl p-4">
                    <h3 id="dossier-decisor" class="text-xs font-semibold text-gray-300">{{ __('messages.decision_maker') }}</h3>
                    @if ($lead->decisor_nome)
                        <p class="mt-1 text-sm text-white">
                            {{ $lead->decisor_nome }}
                            @if ($lead->decisor_cargo) <span class="text-gray-400">· {{ $lead->decisor_cargo }}</span> @endif
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            @if (isset($dossie['decisor_fonte']))
                                {{ __('messages.decision_maker_source_web') }}
                                @if ($decisorFonte)
                                    <a href="{{ $decisorFonte }}" target="_blank" rel="noopener noreferrer nofollow" class="text-blue-300 hover:text-blue-200 underline break-all">{{ parse_url($decisorFonte, PHP_URL_HOST) }}</a>
                                @endif
                            @else
                                {{ __('messages.decision_maker_source_registry') }}
                            @endif
                        </p>
                    @else
                        <p class="mt-1 text-sm text-gray-400">{{ __('messages.decision_maker_unknown') }}</p>
                    @endif
                </section>

                {{-- Contatos --}}
                <section aria-labelledby="dossier-contatos" class="bg-gray-800/60 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <h3 id="dossier-contatos" class="text-xs font-semibold text-gray-300">{{ __('messages.contacts_found') }}</h3>
                        <button type="button" wire:click="buscarContatos"
                                @disabled(in_array($lead->enrichment_status, ['pending', 'running'], true))
                                class="text-xs text-emerald-300 hover:text-emerald-200 disabled:opacity-50 disabled:cursor-not-allowed rounded
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                            {{ $lead->enriched_at ? __('messages.find_contacts_again') : __('messages.find_contacts') }}
                        </button>
                    </div>

                    <x-contact-badge :status="$lead->enrichment_status" :confidence="$lead->contact_confidence" :origin="$contatos->firstWhere('is_primary', true)?->origem" />

                    <ul class="space-y-3">
                        @forelse ($contatos as $contato)
                            @php
                                $isPhone = $contato->isPhone();
                                $shown = $isPhone ? \App\Support\Phone::display($contato->valor_e164 ?? $contato->valor, $country) : $contato->valor;
                                $waContact = $isPhone && ($contato->tipo === 'whatsapp' || in_array($contato->line_type, ['mobile', 'fixed_or_mobile'], true))
                                    ? \App\Support\Phone::waMeLink($contato->valor_e164 ?? $contato->valor, $country) : null;
                            @endphp
                            <li class="flex items-start justify-between gap-3 text-sm">
                                <div class="min-w-0">
                                    <p class="text-gray-100 break-words">
                                        <span class="text-gray-400">{{ __('messages.contact_type_' . $contato->tipo) }}:</span>
                                        <span class="font-mono">{{ $shown }}</span>
                                        @if ($contato->is_primary) <span class="text-xs text-emerald-300">· {{ __('messages.primary_contact') }}</span> @endif
                                        @if ($contato->provavel_decisor) <span class="text-xs text-violet-300">· {{ __('messages.probable_decision_maker') }}</span> @endif
                                    </p>
                                    <p class="text-xs text-gray-400 break-words">
                                        {{ __('messages.contact_origin_label', ['origem' => __('messages.contact_origin_' . $contato->origem)]) }}@if ($contato->evidencia) — {{ $contato->evidencia }}@endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    @if ($isPhone)
                                        <span class="text-xs tabular-nums text-gray-300" title="{{ __('messages.whatsapp_confidence_hint') }}">
                                            {{ $contato->confianca }}<span class="sr-only"> {{ __('messages.of_100') }}</span>
                                        </span>
                                    @endif
                                    @if ($waContact)
                                        <a href="{{ $waContact }}" target="_blank" rel="noopener noreferrer"
                                           aria-label="{{ __('messages.open_whatsapp_with', ['numero' => $shown]) }}"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[#25D366]/10 hover:bg-[#25D366]/20 text-[#25D366] border border-[#25D366]/30
                                                  focus:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366]">
                                            <x-icons.whatsapp class="w-4 h-4" />
                                        </a>
                                    @elseif ($isPhone)
                                        <a href="tel:{{ $contato->valor_e164 ?? $contato->valor }}"
                                           class="px-2.5 py-1 text-xs rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300">
                                            {{ __('messages.call') }}
                                        </a>
                                    @elseif ($contato->tipo === 'email')
                                        <a href="mailto:{{ $contato->valor }}" class="text-xs text-blue-300 hover:text-blue-200 underline">{{ __('messages.send_email') }}</a>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="text-sm text-gray-400">{{ __('messages.no_contacts_yet') }}</li>
                        @endforelse
                    </ul>
                </section>

                {{-- Sinais --}}
                @php
                    $sinais = array_values(array_filter([
                        !empty($ai['rating']) ? __('messages.signal_rating', ['nota' => number_format((float) $ai['rating'], 1, ',', ''), 'total' => (int) ($ai['user_ratings_total'] ?? 0)]) : null,
                        empty($lead->website) ? __('messages.signal_no_site') : null,
                        $lead->porte ? __('messages.signal_size', ['porte' => $lead->porte]) : null,
                        $anos !== null ? trans_choice('messages.signal_years', $anos, ['anos' => $anos]) : null,
                        $lead->distancia_km !== null ? __('messages.signal_distance', ['km' => number_format((float) $lead->distancia_km, 1, ',', '')]) : null,
                    ]));
                @endphp
                <section aria-labelledby="dossier-sinais" class="bg-gray-800/60 rounded-xl p-4 space-y-2">
                    <h3 id="dossier-sinais" class="text-xs font-semibold text-gray-300">{{ __('messages.signals') }}</h3>
                    @if ($sinais)
                        <ul class="flex flex-wrap gap-1.5">
                            @foreach ($sinais as $sinal)
                                <li class="text-xs px-2 py-0.5 rounded-full bg-gray-900 border border-gray-700 text-gray-200">{{ $sinal }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if (!empty($dossie['resumo']) || !empty($dossie['sobre']))
                        <p class="text-sm text-gray-300">{{ \Illuminate\Support\Str::limit($dossie['resumo'] ?? $dossie['sobre'], 280) }}</p>
                    @endif
                    @if ($reviews)
                        <details class="text-sm">
                            <summary class="cursor-pointer select-none text-xs text-gray-300 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                {{ trans_choice('messages.signal_reviews', count($reviews), ['count' => count($reviews)]) }}
                            </summary>
                            <ul class="mt-2 space-y-2">
                                @foreach ($reviews as $review)
                                    <li class="text-gray-300 border-l-2 border-gray-700 pl-3">
                                        @if (!empty($review['nota'])) <span class="text-yellow-300">★ {{ $review['nota'] }}</span> @endif
                                        {{ \Illuminate\Support\Str::limit($review['texto'], 220) }}
                                        @if (!empty($review['quando'])) <span class="text-xs text-gray-400">· {{ $review['quando'] }}</span> @endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    @if (!$sinais && empty($dossie['resumo']) && empty($dossie['sobre']) && !$reviews)
                        <p class="text-sm text-gray-400">{{ __('messages.signals_empty') }}</p>
                    @endif
                </section>

                {{-- Mensagens sugeridas --}}
                <section aria-labelledby="dossier-mensagens" class="bg-gray-800/60 rounded-xl p-4 space-y-3">
                    <h3 id="dossier-mensagens" class="text-xs font-semibold text-gray-300">{{ __('messages.suggested_messages') }}</h3>

                    @if ($draft?->status === 'generating')
                        <p class="flex items-center gap-2 text-sm text-gray-300">
                            <svg class="w-4 h-4 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ __('messages.outreach_generating', ['nome' => $lead->nome]) }}
                        </p>
                    @elseif ($draft?->status === 'approved')
                        <p class="text-sm text-gray-300">{{ __('messages.outreach_scheduled', ['when' => $draft->scheduled_for?->setTimezone($empresa->timezone ?: config('app.timezone'))->format('d/m H:i')]) }}</p>
                        <p class="text-sm text-gray-100 whitespace-pre-line">{{ $draft->texto_final }}</p>
                    @elseif ($draft)
                        @if (!$draft->isFollowUp() && count($draft->variantes ?? []) > 1)
                            <div class="flex flex-wrap gap-1.5" role="group" aria-label="{{ __('messages.outreach_angles') }}">
                                @foreach ($draft->variantes as $variante)
                                    @php $chosen = $draft->variante_escolhida === $variante['angulo']; @endphp
                                    <button type="button" wire:click="escolherVariante(@js($variante['angulo']))" aria-pressed="{{ $chosen ? 'true' : 'false' }}"
                                            class="px-2.5 py-1 text-xs rounded-lg border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400
                                                   {{ $chosen ? 'bg-emerald-600/20 border-emerald-500 text-emerald-200' : 'bg-gray-900 border-gray-700 text-gray-300 hover:bg-gray-700' }}">
                                        {{ __('messages.outreach_angle_' . $variante['angulo']) }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        <p class="text-sm text-gray-100 whitespace-pre-line">{{ $draft->texto_final }}</p>
                        @if ($draft->dor_hipotese)
                            <p class="text-xs text-gray-400"><span class="text-gray-300">{{ __('messages.outreach_pain_hypothesis') }}:</span> {{ $draft->dor_hipotese }}</p>
                        @endif
                        <a href="{{ route('prospeccao.index', ['passo' => 'abordagens']) }}"
                           class="inline-flex text-xs text-emerald-300 hover:text-emerald-200 underline rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                            {{ __('messages.edit_and_send_in_queue') }}
                        </a>
                    @else
                        <p class="text-sm text-gray-400">{{ __('messages.suggested_messages_empty') }}</p>
                        <button type="button" wire:click="gerarAbordagem" wire:loading.attr="disabled" wire:target="gerarAbordagem"
                                class="px-3 py-1.5 text-xs font-medium bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors disabled:opacity-50
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                            {{ __('messages.generate_outreach') }}
                        </button>
                    @endif
                </section>

                {{-- Linha do tempo --}}
                <section aria-labelledby="dossier-timeline" class="bg-gray-800/60 rounded-xl p-4">
                    <h3 id="dossier-timeline" class="text-xs font-semibold text-gray-300 mb-3">{{ __('messages.timeline') }}</h3>
                    <ol class="relative border-l border-gray-700 ml-1.5 space-y-3">
                        @foreach ($timeline as $event)
                            <li class="pl-4 relative">
                                <span class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                <p class="text-sm text-gray-100">{{ $event['text'] }}</p>
                                <p class="text-xs text-gray-400">
                                    <time datetime="{{ $event['when']->toIso8601String() }}">{{ $event['when']->setTimezone($empresa->timezone ?: config('app.timezone'))->format('d/m/Y H:i') }}</time>
                                </p>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            {{-- Ações --}}
            <footer class="flex flex-wrap items-center gap-2 px-5 py-3 border-t border-gray-800 bg-gray-900">
                @if ($waLink)
                    <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 px-3 py-2 text-sm rounded-xl bg-[#25D366]/10 hover:bg-[#25D366]/20 text-[#25D366] border border-[#25D366]/30
                              focus:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366]">
                        <x-icons.whatsapp class="w-4 h-4" />
                        {{ __('messages.open_in_my_whatsapp') }}
                    </a>
                @endif
                @if ($waitingReply)
                    <button type="button" wire:click="registrarResposta" title="{{ __('messages.outreach_he_replied_hint') }}"
                            class="px-3 py-2 text-sm rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                        {{ __('messages.lead_replied') }}
                    </button>
                @endif
                @if ($website)
                    <a href="{{ $website }}" target="_blank" rel="noopener noreferrer nofollow"
                       class="px-3 py-2 text-sm rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300">
                        {{ __('messages.view_site') }}
                    </a>
                @endif
                @if ($mapsLink)
                    <a href="{{ $mapsLink }}" target="_blank" rel="noopener noreferrer"
                       class="px-3 py-2 text-sm rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300">
                        {{ __('messages.view_on_maps') }}
                    </a>
                @endif
            </footer>
        </aside>
    </div>
@endif
</div>
