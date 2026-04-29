<x-app-layout>
    <x-slot name="title">{{ __('messages.edit_store') }}</x-slot>

    <div class="max-w-2xl mx-auto">
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-lg font-bold text-white mb-6">{{ __('messages.edit_store') }}: {{ $loja->nome }}</h2>

            <form action="{{ route('lojas.update', $loja) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PUT')
                @include('lojas.partials.form')
                <div class="flex justify-between pt-2">
                    <form action="{{ route('lojas.destroy', $loja) }}" method="POST"
                          onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-4 py-2 text-red-400 hover:text-red-300 text-sm transition-colors">
                            {{ __('messages.delete') }}
                        </button>
                    </form>
                    <div class="flex gap-3">
                        <a href="{{ route('lojas.index') }}"
                           class="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors">
                            {{ __('messages.cancel') }}
                        </a>
                        <button type="submit"
                                class="px-6 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-xl transition-colors">
                            {{ __('messages.save') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
