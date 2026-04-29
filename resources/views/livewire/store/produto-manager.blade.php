<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-white">{{ __('messages.products') }}</h2>
        <button wire:click="openCreate"
                class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-xl transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('messages.add_product') }}
        </button>
    </div>

    {{-- Grid --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @forelse ($produtos as $produto)
            <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden group">
                <div class="aspect-square bg-gray-800 relative overflow-hidden">
                    <img src="{{ $produto->imagem_url }}"
                         alt="{{ $produto->nome }}"
                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                         onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($produto->nome) }}&background=1f2937&color=9ca3af'">
                    <div class="absolute top-2 right-2 flex gap-1">
                        <button wire:click="openEdit({{ $produto->id }})"
                                class="w-7 h-7 bg-gray-900/80 hover:bg-gray-800 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button wire:click="delete({{ $produto->id }})"
                                wire:confirm="{{ __('messages.confirm_delete') }}"
                                class="w-7 h-7 bg-red-900/80 hover:bg-red-800 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="w-3.5 h-3.5 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="p-3">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-medium text-white leading-tight truncate">{{ $produto->nome }}</p>
                        <button wire:click="toggleAtivo({{ $produto->id }})"
                                class="flex-shrink-0 w-4 h-4 rounded-full mt-0.5 transition-colors
                                       {{ $produto->ativo ? 'bg-green-500' : 'bg-gray-600' }}">
                        </button>
                    </div>
                    <p class="text-sm font-bold text-green-400 mt-1">{{ $produto->preco_formatado }}</p>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12 text-gray-500">
                {{ __('messages.no_products') }}
            </div>
        @endforelse
    </div>

    {{ $produtos->links() }}

    {{-- Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
             x-data x-init x-on:keydown.escape.window="$wire.set('showModal', false)">
            <div class="bg-gray-900 border border-gray-800 rounded-2xl w-full max-w-md mx-4 shadow-2xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                    <h3 class="text-base font-semibold text-white">
                        {{ $editingId ? __('messages.edit_product') : __('messages.add_product') }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.name') }}</label>
                        <input wire:model="nome" type="text"
                               class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
                        @error('nome') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.price') }}</label>
                        <input wire:model="preco" type="number" step="0.01" min="0"
                               class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
                        @error('preco') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.description') }}</label>
                        <textarea wire:model="descricao" rows="3"
                                  class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.image') }}</label>
                        <input wire:model="imagem" type="file" accept="image/*"
                               class="w-full text-sm text-gray-400 file:mr-3 file:px-3 file:py-1.5 file:bg-gray-700 file:text-gray-200 file:border-0 file:rounded-lg file:cursor-pointer">
                        @error('imagem') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-3">
                        <input wire:model="ativo" type="checkbox" id="ativo" class="w-4 h-4 accent-green-500">
                        <label for="ativo" class="text-sm text-gray-300">{{ __('messages.active') }}</label>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-800 flex justify-end gap-3">
                    <button wire:click="$set('showModal', false)"
                            class="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors">
                        {{ __('messages.cancel') }}
                    </button>
                    <button wire:click="save"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-xl transition-colors">
                        <span wire:loading.remove wire:target="save">{{ __('messages.save') }}</span>
                        <span wire:loading wire:target="save">{{ __('messages.saving') }}...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
