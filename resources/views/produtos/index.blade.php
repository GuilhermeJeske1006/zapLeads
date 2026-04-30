<x-app-layout>
    <x-slot name="title">{{ __('messages.products') }}</x-slot>

    <div class="space-y-6">
        <div class="bg-gray-900/40 border border-gray-800 rounded-2xl p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-lg font-semibold text-white">{{ __('messages.products') }}</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ __('messages.products_screen_desc') }}</p>
                </div>

                <a href="{{ route('empresa.edit') }}"
                   class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-sm font-medium rounded-xl transition-colors">
                    {{ __('messages.back_to_company') }}
                </a>
            </div>

            <div class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="bg-gray-950/40 border border-gray-800 rounded-2xl p-4">
                    <p class="text-sm font-semibold text-white">{{ __('messages.how_it_works') }}</p>
                    <ul class="mt-2 text-sm text-gray-400 space-y-1.5 list-disc pl-5">
                        <li>{{ __('messages.products_help_add') }}</li>
                        <li>{{ __('messages.products_help_edit') }}</li>
                        <li>{{ __('messages.products_help_toggle') }}</li>
                        <li>{{ __('messages.products_help_delete') }}</li>
                    </ul>
                </div>

                <div class="bg-gray-950/40 border border-gray-800 rounded-2xl p-4">
                    <p class="text-sm font-semibold text-white">{{ __('messages.tips') }}</p>
                    <ul class="mt-2 text-sm text-gray-400 space-y-1.5 list-disc pl-5">
                        <li>{{ __('messages.products_help_active_only') }}</li>
                        <li>{{ __('messages.products_help_image') }}</li>
                        <li>{{ __('messages.products_help_price') }}</li>
                    </ul>
                </div>

                <div class="bg-gray-950/40 border border-gray-800 rounded-2xl p-4">
                    <p class="text-sm font-semibold text-white">{{ __('messages.where_it_appears') }}</p>
                    <p class="mt-2 text-sm text-gray-400">{{ __('messages.products_help_catalog') }}</p>
                    <div class="mt-3">
                        @if (!empty($empresa->slug))
                            <a href="{{ route('catalogo.show', $empresa->slug) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 text-sm font-medium text-green-400 hover:text-green-300">
                                {{ __('messages.view_catalog') }}
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 3h7m0 0v7m0-7L10 14m-4 7h11a2 2 0 002-2V9"/>
                                </svg>
                            </a>
                        @else
                            <p class="text-sm text-gray-500">{{ __('messages.products_help_catalog_slug_hint') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-gray-900/40 border border-gray-800 rounded-2xl p-6">
            <livewire:store.produto-manager :empresa="$empresa" />
        </div>
    </div>
</x-app-layout>
