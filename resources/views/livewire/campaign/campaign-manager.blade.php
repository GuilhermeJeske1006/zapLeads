<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-white">{{ __('messages.campaigns') }}</h2>
        <button wire:click="openModal"
                class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-xl transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nova Campanha
        </button>
    </div>

    {{-- Table --}}
    <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left">Nome</th>
                    <th class="px-5 py-3 text-left">Status</th>
                    <th class="px-5 py-3 text-left">Enviados</th>
                    <th class="px-5 py-3 text-left">Erros</th>
                    <th class="px-5 py-3 text-left">Criado</th>
                    <th class="px-5 py-3 text-left">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/50">
                @forelse ($campaigns as $campaign)
                    <tr class="hover:bg-gray-800/30 transition-colors">
                        <td class="px-5 py-3 font-medium text-white">{{ $campaign->nome }}</td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ match($campaign->status) {
                                    'completed' => 'bg-green-500/20 text-green-400',
                                    'running' => 'bg-blue-500/20 text-blue-400',
                                    'failed' => 'bg-red-500/20 text-red-400',
                                    'scheduled' => 'bg-yellow-500/20 text-yellow-400',
                                    default => 'bg-gray-700 text-gray-400',
                                } }}">
                                {{ $campaign->status }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-300">{{ $campaign->total_enviados }}</td>
                        <td class="px-5 py-3 text-gray-300">{{ $campaign->total_erros }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ $campaign->created_at->format('d/m/Y') }}</td>
                        <td class="px-5 py-3">
                            @if ($campaign->status === 'draft')
                                <button wire:click="dispatch_campaign({{ $campaign->id }})"
                                        wire:confirm="Confirma disparo da campanha?"
                                        class="px-3 py-1 bg-green-600 hover:bg-green-500 text-white text-xs rounded-lg transition-colors">
                                    Disparar
                                </button>
                            @elseif ($campaign->status === 'running')
                                <span class="text-xs text-blue-400 flex items-center gap-1">
                                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    Enviando...
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-500">
                            Nenhuma campanha ainda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($campaigns->hasPages())
            <div class="px-5 py-4 border-t border-gray-800">{{ $campaigns->links() }}</div>
        @endif
    </div>

    {{-- AI Suggestion Panel --}}
    @if ($showAISuggest && $aiSuggestion)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div class="bg-gray-900 border border-gray-800 rounded-2xl w-full max-w-md mx-4 shadow-2xl p-6">
                <h3 class="text-base font-semibold text-white mb-4">✨ Sugestão da IA</h3>
                <div class="space-y-3 text-sm text-gray-300 mb-5">
                    @if (isset($aiSuggestion['mensagem']))
                        <div class="bg-gray-800 rounded-xl p-3">
                            <p class="text-xs text-green-400 font-medium mb-1">Mensagem sugerida</p>
                            <p>{{ $aiSuggestion['mensagem'] }}</p>
                        </div>
                    @endif
                    @if (isset($aiSuggestion['horario']))
                        <div class="bg-gray-800 rounded-xl p-3">
                            <p class="text-xs text-blue-400 font-medium mb-1">Melhor horário</p>
                            <p>{{ $aiSuggestion['horario'] }}</p>
                        </div>
                    @endif
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('showAISuggest', false)"
                            class="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors">Fechar</button>
                    <button wire:click="applyAISuggestion"
                            class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-xl transition-colors">
                        Aplicar mensagem
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Create Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
             x-data x-on:keydown.escape.window="$wire.set('showModal', false)">
            <div class="bg-gray-900 border border-gray-800 rounded-2xl w-full max-w-lg mx-4 shadow-2xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                    <h3 class="text-base font-semibold text-white">Nova Campanha</h3>
                    <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Nome da campanha</label>
                        <input wire:model="nome" type="text"
                               class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
                        @error('nome') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-xs font-medium text-gray-400">Mensagem</label>
                            <button wire:click="getAISuggestion"
                                    wire:loading.attr="disabled"
                                    class="text-xs px-2 py-1 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg transition-colors flex items-center gap-1">
                                <span wire:loading.remove wire:target="getAISuggestion">✨ Sugerir com IA</span>
                                <span wire:loading wire:target="getAISuggestion">Gerando...</span>
                            </button>
                        </div>
                        <textarea wire:model="mensagem" rows="4"
                                  class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors resize-none"></textarea>
                        @error('mensagem') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Imagem (opcional)</label>
                        <input wire:model="imagem" type="file" accept="image/*"
                               class="w-full text-sm text-gray-400 file:mr-3 file:px-3 file:py-1.5 file:bg-gray-700 file:text-gray-200 file:border-0 file:rounded-lg file:cursor-pointer">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                            <input wire:model="filterNearby" type="checkbox" class="w-4 h-4 accent-green-500">
                            📍 Apenas próximos
                        </label>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Score mínimo</label>
                            <input wire:model="minScore" type="number" min="0" max="100"
                                   class="w-full px-2 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-green-500">
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-800 flex justify-end gap-3">
                    <button wire:click="$set('showModal', false)"
                            class="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors">Cancelar</button>
                    <button wire:click="save"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-xl transition-colors">
                        <span wire:loading.remove wire:target="save">Criar campanha</span>
                        <span wire:loading wire:target="save">Salvando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
