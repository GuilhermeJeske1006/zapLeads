<x-app-layout>
    <x-slot name="title">{{ __('messages.empresa') }}</x-slot>

    <div class="space-y-6">
        <div class="bg-gray-900/40 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('messages.edit_empresa') }}</h2>

            <form action="{{ route('empresa.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PUT')

                @include('empresa.partials.form', ['empresa' => $empresa])

                <div class="flex justify-end">
                    <button type="submit"
                            class="px-6 py-2.5 bg-green-600 hover:bg-green-500 text-white font-medium rounded-xl transition-colors">
                        {{ __('messages.save') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-gray-900/40 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-white mb-1">{{ __('messages.ai_personality') }}</h2>
            <p class="text-sm text-gray-500 mb-4">{{ __('messages.ai_personality_desc') }}</p>

            <form action="{{ route('empresa.update') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                {{-- campos obrigatórios invisíveis para não falhar validação --}}
                <input type="hidden" name="whatsapp" value="{{ $empresa->whatsapp }}">
                <input type="hidden" name="endereco" value="{{ $empresa->endereco }}">
                <input type="hidden" name="cidade" value="{{ $empresa->cidade }}">

                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.instructions_for_ai') }}</label>
                    <textarea name="ai_persona" rows="5"
                              placeholder="Ex: Você é a Ana, atendente da {{ $empresa->nome ?? 'nossa empresa' }}. Seja sempre simpática, responda em português informal, nunca mencione concorrentes e foque em entender o problema do cliente antes de oferecer soluções."
                              class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors resize-none">{{ old('ai_persona', $empresa->ai_persona) }}</textarea>
                    @error('ai_persona') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-600 mt-1">{{ __('messages.max_chars_ai') }}</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="px-6 py-2.5 bg-green-600 hover:bg-green-500 text-white font-medium rounded-xl transition-colors">
                        {{ __('messages.save') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-gray-900/40 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('messages.whatsapp_channels_title') }}</h2>
            <p class="text-sm text-gray-500 mb-4">{{ __('messages.whatsapp_channel_desc') }}</p>
            <livewire:whatsapp-channels :empresa-id="$empresa->id" />
        </div>

        <div class="bg-gray-900/40 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('messages.products') }}</h2>
            <livewire:store.produto-manager :empresa="$empresa" />
        </div>
    </div>
</x-app-layout>

