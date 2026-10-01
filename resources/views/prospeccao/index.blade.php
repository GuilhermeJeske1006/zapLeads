<x-app-layout>
    <x-slot name="title">{{ __('messages.nav_prospecting') }}</x-slot>

    <livewire:prospecting.prospecting-wizard :empresa="$empresa" />

    <livewire:leads.lead-dossier :empresa="$empresa" />
</x-app-layout>
