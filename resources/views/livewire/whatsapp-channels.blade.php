<div class="space-y-4">
    {{-- Form --}}
    <form wire:submit="save" class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm text-gray-400 mb-1">Nome do canal</label>
                <input wire:model="nome" type="text" placeholder="Ex: Vendas, Suporte"
                    class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                @error('nome') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm text-gray-400 mb-1">Número Twilio</label>
                <input wire:model="numero" type="text" placeholder="whatsapp:+5511999990000"
                    class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                @error('numero') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer">
                <input wire:model="isDefault" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-green-500 focus:ring-green-500">
                Canal padrão
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer">
                <input wire:model="ativo" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-green-500 focus:ring-green-500">
                Ativo
            </label>
        </div>

        <div class="flex gap-2">
            <button type="submit"
                class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-lg transition-colors">
                {{ $editingId ? 'Salvar alterações' : 'Adicionar canal' }}
            </button>
            @if($editingId)
                <button type="button" wire:click="cancelEdit"
                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition-colors">
                    Cancelar
                </button>
            @endif
        </div>
    </form>

    {{-- Channel list --}}
    @if($channels->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-300">
                <thead>
                    <tr class="text-gray-500 border-b border-gray-800 text-xs uppercase">
                        <th class="pb-2 pr-4">Nome</th>
                        <th class="pb-2 pr-4">Número</th>
                        <th class="pb-2 pr-4">Status</th>
                        <th class="pb-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @foreach($channels as $channel)
                        <tr class="hover:bg-gray-800/30">
                            <td class="py-2.5 pr-4">
                                <span class="font-medium text-white">{{ $channel->nome }}</span>
                                @if($channel->is_default)
                                    <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-900/50 text-green-400 border border-green-800">padrão</span>
                                @endif
                            </td>
                            <td class="py-2.5 pr-4 font-mono text-xs text-gray-400">{{ $channel->numero }}</td>
                            <td class="py-2.5 pr-4">
                                <button wire:click="toggleAtivo({{ $channel->id }})"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium transition-colors
                                        {{ $channel->ativo ? 'bg-green-900/40 text-green-400 hover:bg-green-900/60' : 'bg-gray-700 text-gray-400 hover:bg-gray-600' }}">
                                    {{ $channel->ativo ? 'ativo' : 'inativo' }}
                                </button>
                            </td>
                            <td class="py-2.5">
                                <div class="flex items-center gap-2 justify-end">
                                    @if(!$channel->is_default)
                                        <button wire:click="setDefault({{ $channel->id }})"
                                            class="text-xs text-gray-500 hover:text-green-400 transition-colors">
                                            padrão
                                        </button>
                                    @endif
                                    <button wire:click="edit({{ $channel->id }})"
                                        class="text-xs text-gray-500 hover:text-blue-400 transition-colors">
                                        editar
                                    </button>
                                    <button wire:click="delete({{ $channel->id }})"
                                        wire:confirm="Remover este canal?"
                                        class="text-xs text-gray-500 hover:text-red-400 transition-colors">
                                        remover
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">Nenhum canal configurado. Adicione um número acima.</p>
    @endif
</div>
