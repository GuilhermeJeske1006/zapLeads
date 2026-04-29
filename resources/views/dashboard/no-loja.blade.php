<x-app-layout>
    <x-slot name="title">{{ __('messages.dashboard') }}</x-slot>

    <div class="flex items-center justify-center min-h-96">
        <div class="text-center max-w-sm">
            <div class="w-20 h-20 mx-auto mb-6 bg-gray-800 rounded-2xl flex items-center justify-center">
                <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-white mb-2">{{ __('messages.no_stores_yet') }}</h2>
            <p class="text-gray-400 text-sm mb-6">Crie sua primeira loja para começar a usar o sistema.</p>
            <a href="{{ route('lojas.create') }}"
               class="inline-flex items-center gap-2 px-6 py-3 bg-green-600 hover:bg-green-500 text-white font-medium rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('messages.new_store') }}
            </a>
        </div>
    </div>
</x-app-layout>
