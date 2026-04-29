<x-app-layout>
    <x-slot name="title">{{ __('messages.campaigns') }}</x-slot>

    @if ($loja)
        <livewire:campaign.campaign-manager :loja="$loja" />
    @else
        <div class="text-center py-16 text-gray-500">
            <p class="mb-4">Nenhuma loja cadastrada.</p>
            <a href="{{ route('lojas.create') }}" class="text-green-400 hover:text-green-300 text-sm font-medium">
                Criar primeira loja →
            </a>
        </div>
    @endif
</x-app-layout>
