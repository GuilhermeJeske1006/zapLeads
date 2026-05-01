<div id="onboarding-empresa-step-root" data-autoprompt="{{ $shouldRequestLocation ? '1' : '0' }}" class="grid grid-cols-1 lg:grid-cols-5 shadow-2xl rounded-2xl overflow-hidden">

    {{-- Left: form --}}
    <div class="lg:col-span-3 bg-gray-900 border border-gray-800 lg:border-r-0 lg:rounded-r-none rounded-2xl p-10">
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-white mb-2">{{ __('messages.company_data') }}</h2>
            <p class="text-gray-400 text-sm">{{ __('messages.onboarding_catalog_hint') }}</p>
        </div>

        <form wire:submit="salvar" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">
                    {{ __('messages.company_name') }} <span class="text-red-400">*</span>
                </label>
                <input
                    wire:model="nome"
                    type="text"
                    placeholder="Ex: Moda Feminina da Ana"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
                >
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
                        $wire.set('country', key);
                        this.$nextTick(() => { this.$refs.wpp.value = this.format(this.$refs.wpp.value); });
                    },
                    init() {
                        let digits = ($wire.whatsapp || '').replace(/\D/g, '');
                        if (digits.startsWith('54')) { this.country = 'AR'; $wire.set('country', 'AR'); }
                        this.$nextTick(() => { this.$refs.wpp.value = this.format(this.$refs.wpp.value); });
                    }
                }"
                @click.outside="open = false"
                class="relative"
            >
                <label class="block text-sm font-medium text-gray-300 mb-1.5">
                    WhatsApp <span class="text-red-400">*</span>
                </label>

                <div class="flex">
                    {{-- Country button --}}
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex items-center gap-1.5 shrink-0 px-3 py-2.5 bg-gray-800 border border-gray-700 border-r-0 rounded-l-lg text-sm text-gray-200 hover:bg-gray-700 transition-colors"
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
                        class="absolute top-full left-0 mt-1 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-20 min-w-45"
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
                        wire:model.blur="whatsapp"
                        x-on:input="$event.target.value = format($event.target.value)"
                        type="tel"
                        :placeholder="'+' + cfg.code + ' (11) ' + (cfg.numLen === 9 ? '99999-9999' : '9999-9999')"
                        class="flex-1 min-w-0 bg-gray-800 border border-gray-700 rounded-r-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
                    >
                </div>

                @error('whatsapp') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ __('messages.your_city') }}</label>
                    <input
                        wire:model="cidade"
                        type="text"
                        placeholder="Ex: São Paulo"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
                    >
                    @error('cidade') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ __('messages.address') }}</label>
                    <input
                        wire:model="endereco"
                        type="text"
                        placeholder="Ex: Rua das Flores, 123"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
                    >
                    @error('endereco') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    wire:click="pedirLocalizacao"
                    wire:loading.attr="disabled"
                    class="flex items-center gap-2 text-xs px-4 py-2.5 bg-gray-800 hover:bg-gray-700 disabled:opacity-60 disabled:cursor-not-allowed text-gray-200 rounded-lg transition-colors border border-gray-700"
                >
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                    </svg>
                    {{ __('messages.use_my_location') }}
                </button>

                @if($locationStatus)
                    <p class="text-xs text-gray-400">{{ $locationStatus }}</p>
                @endif
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="w-full bg-green-600 hover:bg-green-500 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold py-3 rounded-lg transition-colors"
            >
                <span wire:loading.remove>{{ __('messages.continue_arrow') }}</span>
                <span wire:loading>{{ __('messages.saving') }}</span>
            </button>
        </form>
    </div>

    {{-- Right: tips panel --}}
    <div class="hidden lg:flex flex-col justify-center lg:col-span-2 bg-gray-800/40 border border-gray-800 rounded-r-2xl p-8">
        <div class="mb-6">
            <div class="w-10 h-10 rounded-xl bg-green-500/15 flex items-center justify-center mb-4">
                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-white mb-1">Seu catálogo público</h3>
            <p class="text-sm text-gray-400">Esses dados aparecem no catálogo digital que seus clientes vão acessar.</p>
        </div>

        <ul class="space-y-4">
            <li class="flex items-start gap-3">
                <div class="w-1.5 h-1.5 rounded-full bg-green-500 mt-2 shrink-0"></div>
                <p class="text-xs text-gray-400">O nome da empresa aparece no topo do catálogo</p>
            </li>
            <li class="flex items-start gap-3">
                <div class="w-1.5 h-1.5 rounded-full bg-green-500 mt-2 shrink-0"></div>
                <p class="text-xs text-gray-400">O WhatsApp recebe os leads capturados pelo catálogo</p>
            </li>
            <li class="flex items-start gap-3">
                <div class="w-1.5 h-1.5 rounded-full bg-green-500 mt-2 shrink-0"></div>
                <p class="text-xs text-gray-400">A localização melhora a prospecção de clientes próximos</p>
            </li>
        </ul>

        <div class="mt-8 p-4 bg-gray-900/60 rounded-xl border border-gray-700/50">
            <p class="text-xs text-gray-500 italic">"Usar minha localização" preenche cidade e endereço automaticamente.</p>
        </div>
    </div>

</div>

@push('scripts')
<script>
function onboardingEmpresaWire() {
    const root = document.getElementById('onboarding-empresa-step-root') || document.currentScript?.closest('[wire\\:id]');
    const compEl = root?.closest('[wire\\:id]') || root;
    const wireId = compEl?.getAttribute?.('wire:id');
    return wireId ? Livewire.find(wireId) : null;
}

function requestOnboardingEmpresaLocation() {
    if (!navigator.geolocation) {
        onboardingEmpresaWire()?.call('falhaLocalizacao', 'Geolocalização não suportada neste navegador.');
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            onboardingEmpresaWire()?.call('receberLocalizacao', pos.coords.latitude, pos.coords.longitude);
        },
        (err) => {
            onboardingEmpresaWire()?.call('falhaLocalizacao', err?.message || 'Permissão negada.');
        },
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
    );
}

window.addEventListener('onboarding-request-location', () => requestOnboardingEmpresaLocation());
document.addEventListener('onboarding-request-location', () => requestOnboardingEmpresaLocation());

(() => {
    const root = document.getElementById('onboarding-empresa-step-root');
    if (root?.dataset?.autoprompt === '1') {
        setTimeout(() => requestOnboardingEmpresaLocation(), 250);
    }
})();
</script>
@endpush
