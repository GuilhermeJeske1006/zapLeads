@php $empresa = $empresa ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.name') }}</label>
        <input name="nome" type="text" value="{{ old('nome', $empresa?->nome) }}"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
        @error('nome') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.whatsapp') }} *</label>
        <input name="whatsapp" type="tel" required value="{{ old('whatsapp', $empresa?->whatsapp) }}"
               placeholder="+55 11 99999-9999"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
        @error('whatsapp') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.city') }} *</label>
        <input name="cidade" type="text" required value="{{ old('cidade', $empresa?->cidade) }}"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
        @error('cidade') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.address') }} *</label>
        <input name="endereco" type="text" required value="{{ old('endereco', $empresa?->endereco) }}"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
        @error('endereco') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.latitude') }}</label>
        <input name="latitude" type="number" step="any" value="{{ old('latitude', $empresa?->latitude) }}"
               id="lat-input"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.longitude') }}</label>
        <input name="longitude" type="number" step="any" value="{{ old('longitude', $empresa?->longitude) }}"
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
               value="{{ old('raio_atendimento', $empresa?->raio_atendimento ?? 10) }}"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.slug') }}</label>
        <input name="slug" type="text" value="{{ old('slug', $empresa?->slug) }}"
               placeholder="minha-empresa"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
        @error('slug') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.logo') }}</label>
        <div x-data="{ preview: null, hasLogo: {{ $empresa?->logo ? 'true' : 'false' }} }"
             class="flex items-center gap-4">
            <div class="shrink-0 w-20 h-20 rounded-xl overflow-hidden bg-gray-800 border border-gray-700 flex items-center justify-center">
                <img x-show="preview || hasLogo"
                     :src="preview || '{{ $empresa?->logo_url ?? '' }}'"
                     alt="Logo"
                     class="w-full h-full object-cover"
                     @if(!$empresa?->logo) style="display:none" @endif>
                <svg x-show="!preview && !hasLogo"
                     class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     @if($empresa?->logo) style="display:none" @endif>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <label class="flex-1 flex flex-col items-center justify-center h-20 border-2 border-dashed border-gray-700 hover:border-green-600/50 rounded-xl cursor-pointer bg-gray-800/50 hover:bg-gray-800 transition-colors group">
                <div class="flex items-center gap-2 text-gray-400 group-hover:text-gray-300 transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span x-show="!preview && !hasLogo" class="text-sm">{{ __('messages.upload_logo') }}</span>
                    <span x-show="preview || hasLogo" class="text-sm" @if(!$empresa?->logo) style="display:none" @endif>{{ __('messages.change_logo') }}</span>
                </div>
                <p class="text-xs text-gray-600 mt-1">PNG, JPG, WebP — max 2MB</p>
                <input name="logo" type="file" accept="image/*" class="hidden"
                       @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null; hasLogo = true">
            </label>
        </div>
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

