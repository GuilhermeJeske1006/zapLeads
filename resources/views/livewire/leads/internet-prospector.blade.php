<div class="space-y-6" wire:poll.1500ms="pollSearch">
    {{-- Form --}}
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 bg-emerald-500/10 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 01.553-.894L9 2m0 18l6-3m-6 3V2m6 15l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 13l-6 3"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-white">Prospecção na Internet (Mapa + Lista)</h3>
                <p class="text-xs text-gray-500">Busca empresas próximas na internet, ranqueia com IA e salva como leads</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5">Descreva sua empresa <span class="text-red-400">*</span></label>
                <textarea wire:model="descricaoEmpresa" rows="3"
                          class="w-full px-3.5 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 placeholder-gray-600
                                 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 transition-colors resize-none"
                          placeholder="Ex: Clínica odontológica, foco em implantes e estética, público classe média a alta..."></textarea>
                @error('descricaoEmpresa') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5">Tipo de cliente que você procura <span class="text-red-400">*</span></label>
                <textarea wire:model="tipoCliente" rows="3"
                          class="w-full px-3.5 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 placeholder-gray-600
                                 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 transition-colors resize-none"
                          placeholder="Ex: Salões de beleza, clínicas estéticas, empresas que precisam de..."></textarea>
                @error('tipoCliente') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-400 mb-1.5">Endereço da empresa <span class="text-red-400">*</span></label>
                <input wire:model="endereco" type="text"
                       class="w-full px-3.5 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 placeholder-gray-600
                              focus:outline-none focus:border-emerald-500 transition-colors"
                       placeholder="Rua, número, bairro">
                @error('endereco') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5">Cidade <span class="text-red-400">*</span></label>
                <input wire:model="cidade" type="text"
                       class="w-full px-3.5 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 placeholder-gray-600
                              focus:outline-none focus:border-emerald-500 transition-colors"
                       placeholder="Cidade/UF">
                @error('cidade') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5">Raio de busca (km) <span class="text-red-400">*</span></label>
                <input wire:model="raioBuscaKm" type="number" min="1" max="50" step="0.5"
                       class="w-full px-3.5 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200
                              focus:outline-none focus:border-emerald-500 transition-colors">
                @error('raioBuscaKm') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <button wire:click="buscar"
                @disabled($buscando)
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-medium rounded-xl transition-colors">
            @if($buscando)
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span>Buscando na fila...</span>
            @else
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <span>Buscar na internet</span>
            @endif
        </button>

        @if ($search && $search->status === 'failed')
            <p class="mt-4 text-sm text-red-400">{{ $search->error }}</p>
        @endif
    </div>

    {{-- Mapa + IA (sempre visível) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-gray-900 border border-gray-800 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-white">Mapa de potenciais clientes</h3>
                    <span class="text-xs text-gray-500">Clique nos pontos para ver detalhes</span>
                </div>
                @if (!$buscaFeita)
                    <p class="text-xs text-gray-500 mb-3">Preencha os dados acima e clique em “Buscar na internet” para plotar os resultados aqui.</p>
                @endif
                <div style="isolation: isolate; position: relative; z-index: 0;" wire:ignore>
                    <div id="internet-map" class="h-80 rounded-xl overflow-hidden bg-gray-800"></div>
                </div>
                @if (!config('services.mapbox.token'))
                    <p class="mt-3 text-xs text-yellow-300/80">
                        Para tiles do Mapbox, defina <span class="font-mono">MAPBOX_TOKEN</span> no <span class="font-mono">.env</span>.
                    </p>
                @endif
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
                <h3 class="text-sm font-semibold text-white mb-4">Resumo</h3>
                <div class="space-y-3 text-sm text-gray-300">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Clientes encontrados</span>
                        <span class="font-semibold text-white">{{ count($resultados) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Raio</span>
                        <span class="font-semibold">{{ number_format($raioBuscaKm, 1) }} km</span>
                    </div>
                    @php
                        $hotCount  = collect($resultados)->filter(fn($l) => (data_get($l,'ai_insights.match_score',0) >= 80))->count();
                        $warmCount = collect($resultados)->filter(fn($l) => (data_get($l,'ai_insights.match_score',0) >= 50 && data_get($l,'ai_insights.match_score',0) < 80))->count();
                    @endphp
                    @if ($hotCount > 0)
                        <div class="flex justify-between">
                            <span class="text-gray-500">Alto match (80%+)</span>
                            <span class="font-semibold text-green-400">{{ $hotCount }}</span>
                        </div>
                    @endif
                    @if ($warmCount > 0)
                        <div class="flex justify-between">
                            <span class="text-gray-500">Médio match (50%+)</span>
                            <span class="font-semibold text-yellow-400">{{ $warmCount }}</span>
                        </div>
                    @endif
                    <div class="pt-2 text-xs text-gray-500">
                        Use “IA enviar” na lista para gerar e disparar a 1ª mensagem via WhatsApp (fila).
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-800">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-white">Lista de potenciais clientes</h3>
                    <span class="text-xs px-2 py-0.5 bg-emerald-500/20 text-emerald-400 rounded-full font-medium">
                        {{ count($resultados) }} encontrados
                    </span>
                </div>

                <button wire:click="verNaTabelaDeLeads"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                               bg-gray-800 hover:bg-gray-700 text-gray-200 border border-gray-700
                               rounded-lg transition-colors">
                    Ver na tabela de leads
                </button>
            </div>

            <table class="w-full text-sm table-fixed">
                <colgroup>
                    <col class="w-[30%]">
                    <col class="w-[16%]">
                    <col class="w-[10%]">
                    <col class="w-[14%]">
                    <col class="w-[15%]">
                    <col class="w-[15%]">
                </colgroup>
                <thead>
                    <tr class="text-xs text-gray-400 uppercase tracking-wide border-b border-gray-800">
                        <th class="px-4 py-3 text-left">Cliente</th>
                        <th class="px-3 py-3 text-left">Telefone</th>
                        <th class="px-3 py-3 text-left">Dist.</th>
                        <th class="px-3 py-3 text-left">Match IA</th>
                        <th class="px-3 py-3 text-left">Status</th>
                        <th class="px-3 py-3 text-left">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    @forelse ($resultados as $lead)
                        @php
                            $matchScore  = data_get($lead, 'ai_insights.match_score', 0);
                            $matchColor  = $matchScore >= 80 ? 'text-green-400 bg-green-500/20'
                                         : ($matchScore >= 50 ? 'text-yellow-400 bg-yellow-500/20'
                                         : 'text-gray-500 bg-gray-700/50');

                            $phone = preg_replace('/\D/', '', (string) ($lead['telefone'] ?? ''));
                            if ($phone && !str_starts_with($phone, '55') && strlen($phone) <= 11) {
                                $phone = '55' . $phone;
                            }
                            $waLink  = $phone ? 'https://wa.me/' . $phone : null;
                            $website = !empty($lead['website']) ? $lead['website'] : null;

                            $status      = $lead['status'] ?? 'novo';
                            $statusMeta  = \App\Models\Lead::STATUSES[$status] ?? \App\Models\Lead::STATUSES['novo'];
                            $statusColor = match($statusMeta['color']) {
                                'blue'   => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                                'yellow' => 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
                                'green'  => 'bg-green-500/20 text-green-400 border-green-500/30',
                                'red'    => 'bg-red-500/20 text-red-400 border-red-500/30',
                                default  => 'bg-gray-700/50 text-gray-400 border-gray-600',
                            };
                        @endphp
                        <tr class="hover:bg-gray-800/30 transition-colors">

                            {{-- Cliente --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-7 h-7 rounded-full bg-linear-to-br from-emerald-500 to-teal-600
                                                flex items-center justify-center text-xs font-bold shrink-0">
                                        {{ strtoupper(substr($lead['nome'] ?? '?', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-white text-xs font-medium truncate" title="{{ $lead['nome'] ?? '' }}">
                                            {{ $lead['nome'] ?? '—' }}
                                        </p>
                                        <p class="text-gray-500 text-xs truncate" title="{{ $lead['endereco'] ?? '' }}">
                                            {{ $lead['endereco'] ?? '' }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            {{-- Telefone --}}
                            <td class="px-3 py-3">
                                <span class="text-gray-400 font-mono text-xs block truncate">{{ $lead['telefone'] ?: '—' }}</span>
                                @if ($website)
                                    <a href="{{ $website }}" target="_blank" rel="noopener noreferrer"
                                       title="{{ $website }}"
                                       class="inline-flex items-center gap-1 text-xs text-blue-400 hover:text-blue-300 transition-colors mt-0.5 truncate max-w-full">
                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                        {{ parse_url($website, PHP_URL_HOST) ?: $website }}
                                    </a>
                                @endif
                            </td>

                            {{-- Distância --}}
                            <td class="px-3 py-3 text-xs text-gray-300">
                                {{ isset($lead['distancia_km']) ? number_format((float) $lead['distancia_km'], 1) . ' km' : '—' }}
                            </td>

                            {{-- Match IA --}}
                            <td class="px-3 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $matchColor }}">
                                    {{ (int) $matchScore }}%
                                </span>
                                @if (!empty($lead['ai_insights']['match_motivo']))
                                    <p class="text-xs text-gray-600 mt-0.5 truncate" title="{{ $lead['ai_insights']['match_motivo'] }}">
                                        {{ $lead['ai_insights']['match_motivo'] }}
                                    </p>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="px-3 py-3">
                                <select wire:change="alterarStatus({{ $lead['id'] }}, $event.target.value)"
                                        class="w-full text-xs px-1.5 py-1 rounded-md border {{ $statusColor }}
                                               bg-transparent cursor-pointer focus:outline-none focus:ring-1 focus:ring-white/20">
                                    @foreach (\App\Models\Lead::STATUSES as $val => $meta)
                                        <option value="{{ $val }}"
                                                {{ $status === $val ? 'selected' : '' }}
                                                class="bg-gray-900 text-gray-200">
                                            {{ $meta['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            {{-- Ações --}}
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1">
                                    @if ($waLink)
                                        <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                                           title="Abrir no WhatsApp"
                                           class="inline-flex items-center justify-center w-7 h-7 bg-[#25D366]/10 hover:bg-[#25D366]/20
                                                  text-[#25D366] border border-[#25D366]/30 rounded-lg transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                            </svg>
                                        </a>

                                        <button wire:click="enviarMensagemIA({{ $lead['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="enviarMensagemIA({{ $lead['id'] }})"
                                                title="Enviar mensagem via IA"
                                                class="inline-flex items-center justify-center w-7 h-7 bg-emerald-600/80 hover:bg-emerald-500
                                                       text-white rounded-lg transition-colors disabled:opacity-50">
                                            <span wire:loading.remove wire:target="enviarMensagemIA({{ $lead['id'] }})">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                </svg>
                                            </span>
                                            <span wire:loading wire:target="enviarMensagemIA({{ $lead['id'] }})">
                                                <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                                </svg>
                                            </span>
                                        </button>
                                    @endif

                                    @if ($website)
                                        <a href="{{ $website }}" target="_blank" rel="noopener noreferrer"
                                           title="Ver site"
                                           class="inline-flex items-center justify-center w-7 h-7 bg-blue-500/10 hover:bg-blue-500/20
                                                  text-blue-400 border border-blue-500/30 rounded-lg transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                            </svg>
                                        </a>
                                    @endif

                                    <button wire:click="abrirModal({{ $lead['id'] }})"
                                            title="Ver detalhes"
                                            class="inline-flex items-center justify-center w-7 h-7 bg-gray-700 hover:bg-gray-600
                                                   text-gray-300 rounded-lg transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500 text-sm">
                                Nenhum resultado ainda. Faça uma busca acima.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</div>

{{-- Modal de detalhes do lead --}}
@if ($showModal && $modalLead)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" wire:click="fecharModal"></div>

        {{-- Panel --}}
        <div class="relative w-full max-w-2xl bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-linear-to-br from-emerald-500 to-teal-600
                                flex items-center justify-center text-sm font-bold text-white shrink-0">
                        {{ strtoupper(substr($modalLead['nome'] ?? '?', 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-white">{{ $modalLead['nome'] ?? '—' }}</h3>
                        <p class="text-xs text-gray-500">{{ $modalLead['endereco'] ?? '' }}</p>
                    </div>
                </div>
                <button wire:click="fecharModal"
                        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">

                {{-- Info grid --}}
                <div class="grid grid-cols-2 gap-4">
                    @php
                        $fields = [
                            ['label' => 'Telefone',   'value' => $modalLead['telefone'] ?: null],
                            ['label' => 'Cidade',     'value' => $modalLead['cidade']   ?: null],
                            ['label' => 'Distância',  'value' => isset($modalLead['distancia_km']) ? number_format((float)$modalLead['distancia_km'], 1).' km' : null],
                            ['label' => 'Proximidade','value' => ($modalLead['is_nearby'] ?? false) ? 'Dentro do raio ✓' : 'Fora do raio'],
                            ['label' => 'Score',      'value' => ($modalLead['lead_score'] ?? 0).' pts'],
                            ['label' => 'Status',     'value' => \App\Models\Lead::STATUSES[$modalLead['status'] ?? 'novo']['label'] ?? 'Novo'],
                            ['label' => 'Fonte',      'value' => $modalLead['source'] ?? $modalLead['external_source'] ?? null],
                            ['label' => 'Cadastrado', 'value' => isset($modalLead['created_at']) ? \Carbon\Carbon::parse($modalLead['created_at'])->format('d/m/Y H:i') : null],
                        ];
                    @endphp
                    @foreach ($fields as $f)
                        @if ($f['value'])
                            <div class="bg-gray-800/60 rounded-xl p-3">
                                <p class="text-xs text-gray-500 mb-0.5">{{ $f['label'] }}</p>
                                <p class="text-sm font-medium text-white">{{ $f['value'] }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Website --}}
                @if (!empty($modalLead['website']))
                    <div class="bg-gray-800/60 rounded-xl p-3">
                        <p class="text-xs text-gray-500 mb-1">Website</p>
                        <a href="{{ $modalLead['website'] }}" target="_blank" rel="noopener noreferrer"
                           class="text-sm text-blue-400 hover:text-blue-300 break-all transition-colors">
                            {{ $modalLead['website'] }}
                        </a>
                    </div>
                @endif

                {{-- AI insights --}}
                @if (!empty($modalLead['ai_insights']))
                    @php $ai = $modalLead['ai_insights']; @endphp
                    <div class="bg-violet-500/5 border border-violet-500/20 rounded-xl p-4 space-y-2">
                        <p class="text-xs font-semibold text-violet-400 uppercase tracking-wide">Análise IA</p>
                        @if (!empty($ai['match_score']))
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400">Match:</span>
                                <span class="text-sm font-bold text-green-400">{{ $ai['match_score'] }}%</span>
                            </div>
                        @endif
                        @if (!empty($ai['match_motivo']))
                            <p class="text-sm text-gray-300">{{ $ai['match_motivo'] }}</p>
                        @endif
                        @if (!empty($ai['rating']))
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400">Avaliação Google:</span>
                                <span class="text-sm font-medium text-yellow-400">★ {{ $ai['rating'] }}</span>
                                @if (!empty($ai['user_ratings_total']))
                                    <span class="text-xs text-gray-500">({{ $ai['user_ratings_total'] }} avaliações)</span>
                                @endif
                            </div>
                        @endif
                        @if (!empty($ai['types']))
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                @foreach ((array)$ai['types'] as $type)
                                    <span class="text-xs px-2 py-0.5 bg-gray-800 text-gray-400 rounded-full">{{ $type }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Footer actions --}}
            <div class="flex items-center gap-2 px-6 py-4 border-t border-gray-800 bg-gray-900/50">
                @php
                    $mPhone = preg_replace('/\D/', '', (string)($modalLead['telefone'] ?? ''));
                    if ($mPhone && !str_starts_with($mPhone, '55') && strlen($mPhone) <= 11) { $mPhone = '55'.$mPhone; }
                    $mWaLink = $mPhone ? 'https://wa.me/'.$mPhone : null;
                @endphp

                @if ($mWaLink)
                    <a href="{{ $mWaLink }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-[#25D366]/10 hover:bg-[#25D366]/20
                              text-[#25D366] border border-[#25D366]/30 text-sm rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Abrir WhatsApp
                    </a>
                    <button wire:click="enviarMensagemIA({{ $modalLead['id'] }}); fecharModal()"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500
                                   text-white text-sm rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Enviar msg IA
                    </button>
                @endif

                @if (!empty($modalLead['website']))
                    <a href="{{ $modalLead['website'] }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500/10 hover:bg-blue-500/20
                              text-blue-400 border border-blue-500/30 text-sm rounded-xl transition-colors ml-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                        </svg>
                        Ver site
                    </a>
                @endif

                <button wire:click="fecharModal"
                        class="ml-auto inline-flex items-center px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm rounded-xl transition-colors">
                    Fechar
                </button>
            </div>
        </div>
    </div>
@endif

@push('scripts')
<script>
(function () {
    const mapboxToken = @json(config('services.mapbox.token'));
    const mapboxStyle = @json(config('services.mapbox.style', 'mapbox/streets-v12'));
    let map = null;
    let markersLayer = null;
    let radiusCircle = null;

    function initMap(loja) {
        const el = document.getElementById('internet-map');
        if (!el || map) return;
        if (!loja?.latitude || !loja?.longitude) return;

        map = L.map('internet-map').setView([loja.latitude, loja.longitude], 13);

        if (mapboxToken) {
            L.tileLayer(`https://api.mapbox.com/styles/v1/${mapboxStyle}/tiles/{z}/{x}/{y}?access_token=${mapboxToken}`, {
                tileSize: 512, zoomOffset: -1, attribution: '© OpenStreetMap © Mapbox'
            }).addTo(map);
        } else {
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(map);
        }

        markersLayer = L.layerGroup().addTo(map);

        const storeIcon = L.divIcon({
            className: '',
            html: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="36" viewBox="0 0 28 36">
                <path d="M14 0C6.27 0 0 6.27 0 14c0 9.625 14 22 14 22s14-12.375 14-22C28 6.27 21.73 0 14 0z" fill="#10b981" stroke="#fff" stroke-width="1.5"/>
                <circle cx="14" cy="14" r="6" fill="#fff"/>
            </svg>`,
            iconSize: [28, 36],
            iconAnchor: [14, 36],
            popupAnchor: [0, -36],
        });

        L.marker([loja.latitude, loja.longitude], { icon: storeIcon })
            .bindPopup(`<b>${loja.nome}</b><br>Sua empresa`)
            .addTo(map);

        radiusCircle = L.circle([loja.latitude, loja.longitude], {
            radius: (loja.raio_atendimento || 5) * 1000,
            color: '#10b981', fillColor: '#10b981', fillOpacity: 0.06, weight: 1,
        }).addTo(map);

        map.invalidateSize();
    }

    function renderLeads(leads, loja) {
        initMap(loja);
        if (!map || !markersLayer) return;

        markersLayer.clearLayers();
        map.setView([loja.latitude, loja.longitude], 13);
        if (radiusCircle) radiusCircle.setRadius((loja.raio_atendimento || 5) * 1000);

        let bounds = [];
        leads.forEach(lead => {
            const lat = parseFloat(lead.latitude);
            const lng = parseFloat(lead.longitude);
            if (!lat || !lng) return;

            const matchScore = lead.ai_insights?.match_score ?? 0;
            const color = matchScore >= 80 ? '#22c55e' : matchScore >= 50 ? '#f59e0b' : '#6b7280';
            const phone = (lead.telefone || '').toString();
            const popup = [
                `<b>${lead.nome}</b>`,
                lead.endereco || '',
                lead.distancia_km ? `${parseFloat(lead.distancia_km).toFixed(1)} km` : '',
                phone,
            ].filter(Boolean).join('<br>');

            const leadIcon = L.divIcon({
                className: '',
                html: `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="28" viewBox="0 0 28 36">
                    <path d="M14 0C6.27 0 0 6.27 0 14c0 9.625 14 22 14 22s14-12.375 14-22C28 6.27 21.73 0 14 0z" fill="${color}" stroke="#fff" stroke-width="1.5"/>
                    <circle cx="14" cy="14" r="5" fill="#fff" fill-opacity="0.9"/>
                </svg>`,
                iconSize: [22, 28],
                iconAnchor: [11, 28],
                popupAnchor: [0, -28],
            });

            L.marker([lat, lng], { icon: leadIcon }).bindPopup(popup).addTo(markersLayer);

            bounds.push([lat, lng]);
        });

        if (bounds.length > 0) {
            try { map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 }); } catch (_) {}
        }

        map.invalidateSize();
    }

    function handleEvent(detail) {
        const leads = detail?.leads || [];
        const loja  = detail?.loja || detail?.empresa || null;
        // nextTick: garante que o div #internet-map já foi inserido no DOM pelo Livewire
        requestAnimationFrame(() => {
            setTimeout(() => renderLeads(leads, loja), 50);
        });
    }

    function registerListeners() {
        // Browser event
        window.addEventListener('internet-leads-updated', (e) => handleEvent(e.detail));
        window.addEventListener('scroll-to-leads-table', () => {
            const el = document.getElementById('leads-table');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        // Livewire event bus
        if (window.Livewire && !window.__internetProspectorHooked) {
            window.__internetProspectorHooked = true;
            Livewire.on('internet-leads-updated', (params) => {
                const detail = Array.isArray(params) ? params[0] : params;
                handleEvent(detail);
            });
        }
    }

    registerListeners();
    document.addEventListener('livewire:initialized', registerListeners);

    // Render inicial (empresa + eventuais resultados já carregados no server)
    try {
        const initialLeads = @json($resultados);
        const initialLoja = @json($empresa);
        setTimeout(() => handleEvent({ leads: initialLeads || [], empresa: initialLoja || null }), 50);
    } catch (_) {}
})();
</script>
@endpush
