<x-app-layout>
    <x-slot name="title">{{ __('messages.leads') }}</x-slot>

    <div class="space-y-6">

        {{-- Prospecção --}}
        <livewire:leads.internet-prospector :empresa="$empresa" />

        {{-- Table --}}
        <livewire:leads.leads-table :empresa="$empresa" />
    </div>
</x-app-layout>
