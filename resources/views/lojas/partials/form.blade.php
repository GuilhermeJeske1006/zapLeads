@php $loja = $loja ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.name') }} *</label>
        <input name="nome" type="text" required value="{{ old('nome', $loja?->nome) }}"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
        @error('nome') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.whatsapp') }} *</label>
        <input name="whatsapp" type="tel" required value="{{ old('whatsapp', $loja?->whatsapp) }}"
               placeholder="+55 11 99999-9999"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
        @error('whatsapp') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.city') }} *</label>
        <input name="cidade" type="text" required value="{{ old('cidade', $loja?->cidade) }}"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
        @error('cidade') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.address') }} *</label>
        <input name="endereco" type="text" required value="{{ old('endereco', $loja?->endereco) }}"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
        @error('endereco') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.latitude') }}</label>
        <input name="latitude" type="number" step="any" value="{{ old('latitude', $loja?->latitude) }}"
               id="lat-input"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.longitude') }}</label>
        <input name="longitude" type="number" step="any" value="{{ old('longitude', $loja?->longitude) }}"
               id="lon-input"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
    </div>

    <div class="md:col-span-2">
        <button type="button" onclick="getMyLocation()"
                class="text-xs px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg transition-colors">
            📍 {{ __('messages.use_my_location') }}
        </button>
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.service_radius') }} (km)</label>
        <input name="raio_atendimento" type="number" min="1" max="500"
               value="{{ old('raio_atendimento', $loja?->raio_atendimento ?? 10) }}"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.slug') }}</label>
        <input name="slug" type="text" value="{{ old('slug', $loja?->slug) }}"
               placeholder="minha-loja"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
        @error('slug') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.logo') }}</label>
        <input name="logo" type="file" accept="image/*"
               class="w-full text-sm text-gray-400 file:mr-3 file:px-3 file:py-1.5 file:bg-gray-700 file:text-gray-200 file:border-0 file:rounded-lg file:cursor-pointer">
    </div>
</div>

<script>
function getMyLocation() {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(pos => {
        document.getElementById('lat-input').value = pos.coords.latitude.toFixed(7);
        document.getElementById('lon-input').value = pos.coords.longitude.toFixed(7);
    });
}
</script>
