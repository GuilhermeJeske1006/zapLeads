<x-app-layout>
    <x-slot name="title">{{ $loja->nome }} - {{ __('messages.products') }}</x-slot>

    <div class="mb-4">
        <a href="{{ route('lojas.index') }}" class="text-sm text-gray-400 hover:text-white transition-colors flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            {{ __('messages.back_to_stores') }}
        </a>
    </div>

    <livewire:store.produto-manager :loja="$loja" />
</x-app-layout>
