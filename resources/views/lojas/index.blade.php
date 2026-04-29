<x-app-layout>
    <x-slot name="title">{{ __('messages.my_stores') }}</x-slot>

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-white">{{ __('messages.my_stores') }}</h2>
        <a href="{{ route('lojas.create') }}"
           class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-xl transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('messages.new_store') }}
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($lojas as $loja)
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-gray-800 flex items-center justify-center overflow-hidden">
                        @if ($loja->logo)
                            <img src="{{ $loja->logo_url }}" alt="" class="w-full h-full object-cover">
                        @else
                            <span class="text-lg font-bold text-gray-400">{{ strtoupper(substr($loja->nome, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-semibold text-white truncate">{{ $loja->nome }}</h3>
                        <p class="text-xs text-gray-400">{{ $loja->cidade }}</p>
                    </div>
                    <span class="flex-shrink-0 w-2 h-2 rounded-full {{ $loja->ativo ? 'bg-green-500' : 'bg-gray-600' }}"></span>
                </div>

                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="text-xs px-2 py-1 bg-gray-800 rounded-lg text-gray-400">
                        📱 {{ $loja->whatsapp }}
                    </span>
                    <span class="text-xs px-2 py-1 bg-gray-800 rounded-lg text-gray-400">
                        📍 {{ $loja->raio_atendimento }} km
                    </span>
                    <span class="text-xs px-2 py-1 bg-gray-800 rounded-lg text-gray-400">
                        🛍️ {{ $loja->produtos()->count() }} {{ __('messages.products') }}
                    </span>
                </div>

                <div class="flex gap-2">
                    <a href="{{ route('lojas.edit', $loja) }}"
                       class="flex-1 text-center px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-medium rounded-xl transition-colors">
                        {{ __('messages.edit') }}
                    </a>
                    <a href="{{ route('store.produtos', $loja) }}"
                       class="flex-1 text-center px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-medium rounded-xl transition-colors">
                        {{ __('messages.products') }}
                    </a>
                    <a href="{{ route('catalogo.show', $loja->slug) }}" target="_blank"
                       class="flex-1 text-center px-3 py-2 bg-green-600/20 hover:bg-green-600/30 text-green-400 text-xs font-medium rounded-xl transition-colors">
                        {{ __('messages.view_catalog') }}
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-16 text-gray-500">
                <p class="mb-4">{{ __('messages.no_stores_yet') }}</p>
                <a href="{{ route('lojas.create') }}" class="text-green-400 hover:text-green-300 text-sm font-medium">
                    {{ __('messages.create_first_store') }}
                </a>
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $lojas->links() }}</div>
</x-app-layout>
