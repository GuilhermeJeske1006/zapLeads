<div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden"
     @if ($drafts->contains('status', 'generating')) wire:poll.2s @endif>

    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-gray-800">
        <div>
            <h3 class="text-sm font-semibold text-white">{{ __('messages.outreach_queue_title') }}</h3>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('messages.outreach_queue_subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if ($channel)
                <span class="text-xs text-gray-400" title="{{ $channel->nome }} · {{ $channel->numero }}">
                    {{ __('messages.daily_limit_usage', ['used' => $usedToday, 'limit' => $channel->limite_diario_prospeccao]) }}
                </span>
            @endif

            @if (count($selecionados) > 0)
                <button wire:click="aprovarSelecionados" wire:loading.attr="disabled"
                        class="px-3 py-1.5 text-xs font-medium bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 disabled:opacity-50">
                    {{ __('messages.send_selected_via_api', ['count' => count($selecionados)]) }}
                </button>
            @endif
        </div>
    </div>

    @forelse ($drafts as $draft)
        @php
            $lead = $draft->lead;
            $plan = $plans[$draft->id] ?? null;
            $waBase = \App\Support\Phone::waMeLink($lead->telefone, $empresa->country);
        @endphp

        <div wire:key="outreach-draft-{{ $draft->id }}" class="px-5 py-4 border-b border-gray-800/60 last:border-b-0">

            @if ($draft->status === 'generating')
                <div class="flex items-center gap-3 text-sm text-gray-400">
                    <svg class="w-4 h-4 animate-spin text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    {{ __('messages.outreach_generating', ['nome' => $lead->nome]) }}
                </div>

            @elseif ($draft->status === 'approved')
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ $lead->nome }}</p>
                        <p class="text-xs text-gray-400">
                            {{ __('messages.outreach_scheduled', ['when' => $draft->scheduled_for->setTimezone($timezone)->format('d/m H:i')]) }}
                            @if ($draft->whatsappChannel) · {{ $draft->whatsappChannel->nome }} @endif
                        </p>
                    </div>
                    <button wire:click="pular({{ $draft->id }})"
                            class="px-3 py-1.5 text-xs text-gray-300 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                        {{ __('messages.cancel_send') }}
                    </button>
                </div>

            @elseif ($draft->status === 'failed')
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ $lead->nome }}</p>
                        <p class="text-xs text-red-400">{{ __(\App\Services\Prospecting\OutreachException::messageKeyFor((string) $draft->erro)) }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="tentarDeNovo({{ $draft->id }})"
                                class="px-3 py-1.5 text-xs text-white bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                            {{ __('messages.try_again') }}
                        </button>
                        <button wire:click="pular({{ $draft->id }})"
                                class="px-3 py-1.5 text-xs text-gray-300 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                            {{ __('messages.discard') }}
                        </button>
                    </div>
                </div>

            @else
                <div class="flex items-start gap-3" x-data="{ texto: @js((string) $draft->texto_final) }">
                    <input type="checkbox" wire:model.live="selecionados" value="{{ $draft->id }}"
                           @disabled($plan['mode'] === 'blocked')
                           aria-label="{{ __('messages.select_lead', ['nome' => $lead->nome]) }}"
                           class="mt-1 rounded border-gray-600 bg-gray-800 text-emerald-500 focus:ring-emerald-500 disabled:opacity-40">

                    <div class="flex-1 min-w-0 space-y-2">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <p class="text-sm font-medium text-white">{{ $lead->nome }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $lead->telefone }}</p>
                            @if ($lead->cidade)
                                <p class="text-xs text-gray-400">{{ $lead->cidade }}</p>
                            @endif
                        </div>

                        <textarea x-model="texto" @change="$wire.salvarTexto({{ $draft->id }}, texto)" rows="3" maxlength="1000"
                                  aria-label="{{ __('messages.outreach_message_for', ['nome' => $lead->nome]) }}"
                                  class="w-full bg-gray-800 border border-gray-700 rounded-xl px-3 py-2 text-sm text-gray-100
                                         focus:outline-none focus:border-emerald-500 resize-y"></textarea>

                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                            <span :class="texto.length > 300 ? 'text-amber-400' : 'text-gray-400'">
                                <span x-text="texto.length"></span>/300
                            </span>

                            @if ($plan['mode'] === 'session')
                                <span class="text-emerald-400">{{ __('messages.delivery_session_open') }}</span>
                            @elseif ($plan['mode'] === 'template')
                                <span class="text-gray-400">{{ __('messages.delivery_template', ['nome' => $plan['template']]) }}</span>
                            @else
                                <span class="text-amber-400">{{ __($plan['reason']) }}</span>
                            @endif
                        </div>

                        @if ($plan['mode'] === 'template')
                            <details class="text-xs text-gray-400">
                                <summary class="cursor-pointer select-none">{{ __('messages.delivery_template_preview') }}</summary>
                                <p class="mt-2 whitespace-pre-line bg-gray-800/60 rounded-lg p-3 text-gray-200">{{ $plan['preview'] }}</p>
                            </details>
                        @endif

                        <div class="flex flex-wrap gap-2 pt-1">
                            <button @click="$wire.enviarPelaApi({{ $draft->id }}, texto)"
                                    @disabled($plan['mode'] === 'blocked')
                                    class="px-3 py-1.5 text-xs font-medium bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors
                                           focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 disabled:opacity-40 disabled:cursor-not-allowed">
                                {{ __('messages.send_via_api') }}
                            </button>

                            @if ($waBase)
                                <a :href="@js($waBase) + '?text=' + encodeURIComponent(texto)" href="{{ $waBase }}"
                                   target="_blank" rel="noopener noreferrer"
                                   @click="$wire.enviadoPeloMeuWhatsApp({{ $draft->id }}, texto)"
                                   class="px-3 py-1.5 text-xs font-medium bg-[#25D366]/10 hover:bg-[#25D366]/20 text-[#25D366]
                                          border border-[#25D366]/30 rounded-lg transition-colors
                                          focus:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366]">
                                    {{ __('messages.open_in_my_whatsapp') }}
                                </a>
                            @endif

                            <button wire:click="pular({{ $draft->id }})"
                                    class="px-3 py-1.5 text-xs text-gray-300 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors
                                           focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                                {{ __('messages.skip') }}
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @empty
        <p class="px-5 py-6 text-sm text-gray-400">{{ __('messages.outreach_queue_empty') }}</p>
    @endforelse

    <details class="px-5 py-3 border-t border-gray-800 text-xs text-gray-400">
        <summary class="cursor-pointer select-none">{{ __('messages.advanced_settings') }}</summary>
        <label class="mt-3 flex items-start gap-2 cursor-pointer">
            <input type="checkbox" wire:click="alternarEnvioAutomatico" @checked($empresa->prospeccao_envio_automatico)
                   class="mt-0.5 rounded border-gray-600 bg-gray-800 text-emerald-500 focus:ring-emerald-500">
            <span>
                <span class="text-gray-200">{{ __('messages.outreach_auto_send') }}</span>
                <span class="block mt-0.5">{{ __('messages.outreach_auto_send_hint') }}</span>
            </span>
        </label>
    </details>
</div>
