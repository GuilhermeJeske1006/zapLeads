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
            <h2 class="text-lg font-semibold text-white mb-4">Canais WhatsApp</h2>
            <p class="text-sm text-gray-500 mb-4">Cada canal tem um número Twilio. O canal padrão é usado em campanhas e sequências. Conversas respondem sempre pelo canal que recebeu a mensagem.</p>
            <livewire:whatsapp-channels :empresa-id="$empresa->id" />
        </div>

        <div class="bg-gray-900/40 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('messages.products') }}</h2>
            <livewire:store.produto-manager :empresa="$empresa" />
        </div>
    </div>
</x-app-layout>

