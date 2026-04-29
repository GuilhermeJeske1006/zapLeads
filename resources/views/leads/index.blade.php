<x-app-layout>
    <x-slot name="title">{{ __('messages.leads') }}</x-slot>

    <div class="space-y-6">

        {{-- AI Lead Finder + Prospecção --}}
        <livewire:leads.lead-finder :empresa="$empresa" />
        <livewire:leads.internet-prospector :empresa="$empresa" />

        {{-- Table --}}
        <livewire:leads.leads-table :empresa="$empresa" />
    </div>
</x-app-layout>
