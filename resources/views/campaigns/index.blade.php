<x-app-layout>
    <x-slot name="title">{{ __('messages.campaigns') }}</x-slot>

    <livewire:campaign.campaign-manager :empresa="$empresa" />
</x-app-layout>
