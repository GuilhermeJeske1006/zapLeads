<x-app-layout>
    <x-slot name="title">{{ __('messages.leads') }}</x-slot>

    <livewire:leads.leads-table :empresa="$empresa" />

    <livewire:leads.lead-dossier :empresa="$empresa" />
</x-app-layout>
