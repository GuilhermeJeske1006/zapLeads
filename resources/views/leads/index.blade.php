<x-app-layout>
    <x-slot name="title">{{ __('messages.leads') }}</x-slot>

    <div class="space-y-6">

        {{-- AI Lead Finder + Prospecção --}}
        @if ($loja)
            <livewire:leads.lead-finder :loja="$loja" />
            <livewire:leads.internet-prospector :loja="$loja" />
        @endif

        {{-- Table --}}
        @if ($loja)
            <livewire:leads.leads-table :loja="$loja" />
        @endif
    </div>
</x-app-layout>
