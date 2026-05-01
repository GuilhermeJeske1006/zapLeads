@php $empresa = $empresa ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.name') }}</label>
        <input name="nome" type="text" value="{{ old('nome', $empresa?->nome) }}"
               class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-green-500 transition-colors">
        @error('nome') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div
        x-data="{
            open: false,
            country: 'BR',
            countries: {
                BR: { code: '55', flag: '🇧🇷', label: 'Brasil',    dddLen: 2, numLen: 9, split: 5 },
                AR: { code: '54', flag: '🇦🇷', label: 'Argentina', dddLen: 2, numLen: 8, split: 4 }
            },
            get cfg() { return this.countries[this.country]; },
            format(val) {
                let digits = val.replace(/\D/g, '');
                if (digits.startsWith(this.cfg.code)) digits = digits.substring(this.cfg.code.length);
                digits = digits.substring(0, this.cfg.dddLen + this.cfg.numLen);
                if (!digits) return '';
                let r = '+' + this.cfg.code;
                r += ' (' + digits.substring(0, Math.min(this.cfg.dddLen, digits.length));
                if (digits.length > this.cfg.dddLen) {
                    r += ') ' + digits.substring(this.cfg.dddLen, this.cfg.dddLen + this.cfg.split);
                    if (digits.length > this.cfg.dddLen + this.cfg.split)
                        r += '-' + digits.substring(this.cfg.dddLen + this.cfg.split);
                }
                return r;
            },
            selectCountry(key) {
                this.country = key;
                this.open = false;
                this.$nextTick(() => { this.$refs.wpp.value = this.format(this.$refs.wpp.value); });
            },
            init() {
                let digits = ('{{ old('whatsapp', $empresa?->whatsapp) }}').replace(/\D/g, '');
                if (digits.startsWith('54')) this.country = 'AR';
                this.$nextTick(() => { this.$refs.wpp.value = this.format(this.$refs.wpp.value); });
            }
        }"
        @click.outside="open = false"
        class="relative"
    >
        <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.whatsapp') }} *</label>

        <div class="flex">
            {{-- Country button --}}
            <button
                type="button"
                @click="open = !open"
                class="flex items-center gap-1.5 shrink-0 px-3 py-2.5 bg-gray-800 border border-gray-700 border-r-0 rounded-l-xl text-sm text-gray-200 hover:bg-gray-700 transition-colors"
            >
                <span x-text="cfg.flag"></span>
                <span class="text-gray-400 text-xs" x-text="'+' + cfg.code"></span>
                <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            {{-- Dropdown --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute top-full left-0 mt-1 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-20 min-w-44"
            >
                <template x-for="(c, key) in countries" :key="key">
                    <button
                        type="button"
                        @click="selectCountry(key)"
                        class="flex items-center gap-2 w-full px-3 py-2.5 text-sm text-gray-200 hover:bg-gray-700 first:rounded-t-lg last:rounded-b-lg transition-colors"
                        :class="{ 'bg-gray-700/60': country === key }"
                    >
                        <span x-text="c.flag"></span>
                        <span x-text="c.label"></span>
                        <span class="text-gray-500 text-xs ml-auto" x-text="'+' + c.code"></span>
                    </button>
                </template>
            </div>

            {{-- Input --}}
            <input
                x-ref="wpp"
                name="whatsapp"
                x-on:input="$event.target.value = format($event.target.value)"
                type="tel"
                required
                :placeholder="'+' + cfg.code + ' (11) ' + (cfg.numLen === 9 ? '99999-9999' : '9999-9999')"
                class="flex-1 min-w-0 bg-gray-800 border border-gray-700 rounded-r-xl px-4 py-2.5 text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
            >
        </div>

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

