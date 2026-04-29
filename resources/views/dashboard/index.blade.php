<x-app-layout>
    <x-slot name="title">{{ __('messages.dashboard') }}</x-slot>

    @stack('scripts')

    <livewire:dashboard.dashboard-panel :empresa="$empresa" />
</x-app-layout>
