<div id="leads-table">
    {{-- Filters --}}
      <div class="ml-auto flex items-end mb-3 gap-2 pb-1.5">
                <button wire:click="abrirAddModal"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                               bg-green-600/20 hover:bg-green-600/30 text-green-400 border border-green-600/40
                               rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Adicionar lead
                </button>

                <button wire:click="deleteAllLeads"
                        wire:confirm="Excluir TODOS os leads? Esta ação não pode ser desfeita."
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                               bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30
                               rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Excluir todos
                </button>
            </div>
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-40">
                <label class="block text-xs text-gray-500 mb-1">Buscar nome</label>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Digite o nome..."
                       class="w-full px-3 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300
                              placeholder-gray-600 focus:outline-none focus:border-green-500">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Status</label>
                <select wire:model.live="filterStatus"
                        class="px-3 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Lead::STATUSES as $val => $meta)
                        <option value="{{ $val }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer pb-1.5">
                <input wire:model.live="filterNearby" type="checkbox" class="w-4 h-4 accent-green-500">
                📍 Próximos
            </label>

            @if ($hasFilters)
                <button wire:click="limparFiltros" class="text-xs text-gray-400 hover:text-white transition-colors pb-1.5">
                    Limpar
                </button>
            @endif

          
        </div>
    </div>

    <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
        <table class="w-full text-sm table-fixed">
            <colgroup>
                <col class="w-[35%]">
                <col class="w-[18%]">
                <col class="w-[17%]">
                <col class="w-[15%]">
                <col class="w-[15%]">
            </colgroup>
            <thead>
                <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-4 py-3 text-left">Lead</th>
                    <th class="px-3 py-3 text-left">Telefone</th>
                    <th class="px-3 py-3 text-left">Local</th>
                    <th class="px-3 py-3 text-left">Status</th>
                    <th class="px-3 py-3 text-left">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/50">
                @forelse ($leads as $lead)
                    @php
                        $phone = preg_replace('/\D/', '', (string) ($lead->telefone ?? ''));
                        if ($phone && !str_starts_with($phone, '55') && strlen($phone) <= 11) {
                            $phone = '55' . $phone;
                        }
                        $waLink = $phone ? 'https://wa.me/' . $phone : null;

                        $mapsLink = null;
                        if (!empty($lead->latitude) && !empty($lead->longitude)) {
                            $mapsLink = 'https://www.google.com/maps?q=' . $lead->latitude . ',' . $lead->longitude;
                        } else {
                            $q = trim((string) ($lead->endereco ?? ''));
                            if (!$q) {
                                $q = trim((string) ($lead->cidade ?? ''));
                            }
                            if ($q) {
                                $mapsLink = 'https://www.google.com/maps?q=' . rawurlencode($q);
                            }
                        }

                        $status     = $lead->status ?? 'novo';
                        $statusMeta = \App\Models\Lead::STATUSES[$status] ?? \App\Models\Lead::STATUSES['novo'];
                        $statusColor = match($statusMeta['color']) {
                            'blue'   => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                            'yellow' => 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
                            'green'  => 'bg-green-500/20 text-green-400 border-green-500/30',
                            'red'    => 'bg-red-500/20 text-red-400 border-red-500/30',
                            default  => 'bg-gray-700/50 text-gray-400 border-gray-600',
                        };
                    @endphp
                    <tr class="hover:bg-gray-800/30 transition-colors">

                        {{-- Lead --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-7 h-7 rounded-full bg-linear-to-br from-blue-500 to-violet-600
                                            flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($lead->nome, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-white font-medium text-xs truncate" title="{{ $lead->nome }}">
                                        {{ $lead->nome }}
                                    </p>
                                    @if ($lead->endereco)
                                        <p class="text-gray-500 text-xs truncate" title="{{ $lead->endereco }}">
                                            {{ $lead->endereco }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- Telefone --}}
                        <td class="px-3 py-3">
                            <span class="text-gray-400 font-mono text-xs block truncate">{{ $lead->telefone ?: '—' }}</span>
                            @if ($lead->website)
                                <a href="{{ $lead->website }}" target="_blank" rel="noopener noreferrer"
                                   title="{{ $lead->website }}"
                                   class="inline-flex items-center gap-1 text-xs text-blue-400 hover:text-blue-300 transition-colors mt-0.5 truncate max-w-full">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    {{ parse_url($lead->website, PHP_URL_HOST) ?: $lead->website }}
                                </a>
                            @endif
                        </td>

                        {{-- Local --}}
                        <td class="px-3 py-3">
                            <p class="text-gray-300 text-xs truncate">{{ $lead->cidade ?? '—' }}</p>
                            @if ($lead->distancia_km)
                                <p class="text-xs {{ $lead->is_nearby ? 'text-green-400' : 'text-gray-500' }}">
                                    {{ number_format($lead->distancia_km, 1) }} km
                                </p>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-3 py-3">
                            <select wire:change="alterarStatus({{ $lead->id }}, $event.target.value)"
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
                                @if ($mapsLink)
                                    <a href="{{ $mapsLink }}" target="_blank" rel="noopener noreferrer"
                                       title="Ver localização no mapa"
                                       class="inline-flex items-center justify-center w-7 h-7
                                              bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/30
                                              rounded-lg transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M12 11.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 10.5c0 6.5-7 11.5-7 11.5S5 17 5 10.5a7 7 0 1114 0z"/>
                                        </svg>
                                    </a>
                                @endif

                                @if ($waLink)
                                    <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                                       title="Abrir no WhatsApp"
                                       class="inline-flex items-center justify-center w-7 h-7
                                              bg-[#25D366]/10 hover:bg-[#25D366]/20 text-[#25D366]
                                              border border-[#25D366]/30 rounded-lg transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                        </svg>
                                    </a>

                                    <button wire:click="enviarMensagemIA({{ $lead->id }})"
                                            title="IA enviar"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-60 cursor-not-allowed"
                                            wire:target="enviarMensagemIA({{ $lead->id }})"
                                            class="inline-flex items-center justify-center w-7 h-7
                                                   bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30
                                                   rounded-lg transition-colors">
                                        <span wire:loading.remove wire:target="enviarMensagemIA({{ $lead->id }})">IA</span>
                                        <span wire:loading wire:target="enviarMensagemIA({{ $lead->id }})">
                                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                            </svg>
                                        </span>
                                    </button>
                                @endif

                                <button wire:click="abrirModal({{ $lead->id }})"
                                        title="Ver detalhes"
                                        class="inline-flex items-center justify-center w-7 h-7
                                               bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>

                                <button wire:click="deleteLead({{ $lead->id }})"
                                        wire:confirm="Excluir lead '{{ addslashes($lead->nome) }}'?"
                                        title="Excluir"
                                        class="inline-flex items-center justify-center w-7 h-7
                                               bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30
                                               rounded-lg transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-500 text-sm">
                            Nenhum lead encontrado.
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

    {{-- Add Lead Modal --}}
    @teleport('body')
    <div
        x-data
        x-show="$wire.showAddModal"
        x-cloak
        class="fixed inset-0 flex items-center justify-center p-4"
        style="z-index: 9999;"
    >
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" wire:click="fecharAddModal"></div>

        <div class="relative w-full max-w-lg bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                <h3 class="text-base font-semibold text-white">Adicionar lead</h3>
                <button wire:click="fecharAddModal"
                        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form wire:submit="salvarLead" class="px-6 py-5 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-400 mb-1">Nome <span class="text-red-400">*</span></label>
                        <input wire:model="addNome" type="text" placeholder="Nome completo ou empresa"
                            class="w-full px-3 py-2 bg-gray-800 border text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 {{ $errors->has('addNome') ? 'border-red-500' : 'border-gray-700' }}">
                        @error('addNome') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Telefone <span class="text-red-400">*</span></label>
                        <input wire:model="addTelefone" type="text" placeholder="11999990000"
                            class="w-full px-3 py-2 bg-gray-800 border text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 {{ $errors->has('addTelefone') ? 'border-red-500' : 'border-gray-700' }}">
                        @error('addTelefone') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Status</label>
                        <select wire:model="addStatus"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            @foreach (\App\Models\Lead::STATUSES as $val => $meta)
                                <option value="{{ $val }}">{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Cidade</label>
                        <input wire:model="addCidade" type="text" placeholder="São Paulo"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Endereço</label>
                        <input wire:model="addEndereco" type="text" placeholder="Rua..."
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs text-gray-400 mb-1">Website</label>
                        <input wire:model="addWebsite" type="text" placeholder="https://..."
                            class="w-full px-3 py-2 bg-gray-800 border text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 {{ $errors->has('addWebsite') ? 'border-red-500' : 'border-gray-700' }}">
                        @error('addWebsite') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="fecharAddModal"
                        class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm rounded-xl transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="px-5 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-xl transition-colors">
                        Salvar lead
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endteleport

    {{-- Modal --}}
    @teleport('body')
    <div
        x-data
        x-show="$wire.showModal && $wire.modalLead !== null"
        x-cloak
        class="fixed inset-0 flex items-center justify-center p-4"
        style="z-index: 9999;"
    >
    @if ($showModal && $modalLead)
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" wire:click="fecharModal"></div>

            <div class="relative w-full max-w-2xl bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-linear-to-br from-blue-500 to-violet-600
                                    flex items-center justify-center text-sm font-bold text-white shrink-0">
                            {{ strtoupper(substr($modalLead['nome'] ?? '?', 0, 1)) }}
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-white">{{ $modalLead['nome'] ?? '—' }}</h3>
                            <p class="text-xs text-gray-500">{{ $modalLead['endereco'] ?? $modalLead['cidade'] ?? '' }}</p>
                        </div>
                    </div>
                    <button wire:click="fecharModal"
                            class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">
                    <div class="grid grid-cols-2 gap-3">
                        @php
                            $mFields = [
                                ['label' => 'Telefone',    'value' => $modalLead['telefone'] ?: null],
                                ['label' => 'Cidade',      'value' => $modalLead['cidade']   ?: null],
                                ['label' => 'Endereço',    'value' => $modalLead['endereco'] ?: null],
                                ['label' => 'Distância',   'value' => isset($modalLead['distancia_km']) ? number_format((float)$modalLead['distancia_km'], 1).' km' : null],
                                ['label' => 'Proximidade', 'value' => ($modalLead['is_nearby'] ?? false) ? 'Dentro do raio ✓' : 'Fora do raio'],
                                ['label' => 'Score',       'value' => ($modalLead['lead_score'] ?? 0).' pts'],
                                ['label' => 'Status',      'value' => \App\Models\Lead::STATUSES[$modalLead['status'] ?? 'novo']['label'] ?? 'Novo'],
                                ['label' => 'Fonte',       'value' => $modalLead['source'] ?? null],
                                ['label' => 'Cadastrado',  'value' => isset($modalLead['created_at']) ? \Carbon\Carbon::parse($modalLead['created_at'])->format('d/m/Y H:i') : null],
                            ];

                            $modalMapsLink = null;
                            $lat = $modalLead['latitude'] ?? null;
                            $lng = $modalLead['longitude'] ?? null;
                            if ($lat && $lng) {
                                $modalMapsLink = 'https://www.google.com/maps?q=' . $lat . ',' . $lng;
                            } else {
                                $q = trim((string) ($modalLead['endereco'] ?? ''));
                                if (!$q) {
                                    $q = trim((string) ($modalLead['cidade'] ?? ''));
                                }
                                if ($q) {
                                    $modalMapsLink = 'https://www.google.com/maps?q=' . rawurlencode($q);
                                }
                            }
                        @endphp
                        @foreach ($mFields as $f)
                            @if ($f['value'])
                                <div class="bg-gray-800/60 rounded-xl p-3">
                                    <p class="text-xs text-gray-500 mb-0.5">{{ $f['label'] }}</p>
                                    <p class="text-sm font-medium text-white">{{ $f['value'] }}</p>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    @if ($modalMapsLink)
                        <div class="bg-gray-800/60 rounded-xl p-3">
                            <p class="text-xs text-gray-500 mb-1">Localização</p>
                            <a href="{{ $modalMapsLink }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-2 text-sm text-blue-400 hover:text-blue-300 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 11.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 10.5c0 6.5-7 11.5-7 11.5S5 17 5 10.5a7 7 0 1114 0z"/>
                                </svg>
                                Ver no Google Maps
                            </a>
                        </div>
                    @endif

                    @if (!empty($modalLead['website']))
                        <div class="bg-gray-800/60 rounded-xl p-3">
                            <p class="text-xs text-gray-500 mb-1">Website</p>
                            <a href="{{ $modalLead['website'] }}" target="_blank" rel="noopener noreferrer"
                               class="text-sm text-blue-400 hover:text-blue-300 break-all">
                                {{ $modalLead['website'] }}
                            </a>
                        </div>
                    @endif

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
                                    <span class="text-xs text-gray-400">Avaliação:</span>
                                    <span class="text-sm font-medium text-yellow-400">★ {{ $ai['rating'] }}</span>
                                    @if (!empty($ai['user_ratings_total']))
                                        <span class="text-xs text-gray-500">({{ $ai['user_ratings_total'] }})</span>
                                    @endif
                                </div>
                            @endif
                            @if (!empty($ai['sugestao_mensagem']))
                                <div class="bg-gray-800/60 rounded-lg p-2">
                                    <p class="text-xs text-gray-500 mb-1">Sugestão de mensagem</p>
                                    <p class="text-xs text-gray-200">{{ $ai['sugestao_mensagem'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2 flex-wrap px-6 py-4 border-t border-gray-800 bg-gray-900/50">
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
                    @endif

                    @if (!empty($modalLead['website']))
                        <a href="{{ $modalLead['website'] }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500/10 hover:bg-blue-500/20
                                  text-blue-400 border border-blue-500/30 text-sm rounded-xl transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                            </svg>
                            Ver site
                        </a>
                    @endif

                    <button wire:click="fecharModal"
                            class="ml-auto inline-flex items-center px-4 py-2 bg-gray-800 hover:bg-gray-700
                                   text-gray-300 text-sm rounded-xl transition-colors">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif
    </div>
    @endteleport
</div>
