<div class="bg-gray-900 border border-gray-800 rounded-2xl p-8">
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-white mb-2">Dados da sua empresa</h2>
        <p class="text-gray-400 text-sm">Essas informações aparecem no seu catálogo digital.</p>
    </div>

    <form wire:submit="salvar" class="space-y-5">
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">
                Nome da empresa <span class="text-red-400">*</span>
            </label>
            <input
                wire:model="nome"
                type="text"
                placeholder="Ex: Moda Feminina da Ana"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
            >
            @error('nome') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">
                WhatsApp <span class="text-red-400">*</span>
            </label>
            <input
                wire:model="whatsapp"
                type="text"
                placeholder="Ex: +55 11 99999-9999"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
            >
            @error('whatsapp') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Cidade</label>
                <input
                    wire:model="cidade"
                    type="text"
                    placeholder="Ex: São Paulo"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
                >
                @error('cidade') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Endereço</label>
                <input
                    wire:model="endereco"
                    type="text"
                    placeholder="Ex: Rua das Flores, 123"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
                >
                @error('endereco') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full bg-green-600 hover:bg-green-500 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold py-3 rounded-lg transition-colors mt-2"
        >
            <span wire:loading.remove>Continuar →</span>
            <span wire:loading>Salvando...</span>
        </button>
    </form>
</div>
