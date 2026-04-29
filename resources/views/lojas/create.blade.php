<x-app-layout>
    <x-slot name="title">{{ __('messages.new_store') }}</x-slot>

    <div class="max-w-2xl mx-auto">
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-lg font-bold text-white mb-6">{{ __('messages.new_store') }}</h2>

            <form action="{{ route('lojas.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @include('lojas.partials.form')
                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('lojas.index') }}"
                       class="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors">
                        {{ __('messages.cancel') }}
                    </a>
                    <button type="submit"
                            class="px-6 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-xl transition-colors">
                        {{ __('messages.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
