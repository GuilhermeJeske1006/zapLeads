<x-app-layout>
    <x-slot name="title">{{ __('messages.dashboard') }}</x-slot>

    @stack('scripts')

    <livewire:dashboard.dashboard-panel :loja="$loja" />
</x-app-layout>
