<div>
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-white mb-2">Escolha seu plano</h2>
        <p class="text-gray-400 text-sm">{{ __('messages.free_for_x_days', ['days' => $plan['trial_days']]) }}, sem cobrar nada agora.</p>
    </div>

    <div class="bg-gray-900 border-2 border-green-500/60 rounded-2xl p-8 relative">
        <div class="absolute -top-3 left-1/2 -translate-x-1/2">
            <span class="bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                {{ __('messages.free_for_x_days', ['days' => $plan['trial_days']]) }}
            </span>
        </div>

        <div class="text-center mb-8">
            <h3 class="text-xl font-bold text-white mb-1">{{ $plan['name'] }}</h3>
            <div class="flex items-baseline justify-center gap-1 mt-4">
                <span class="text-gray-400 text-sm">R$</span>
                <span class="text-5xl font-bold text-white">{{ number_format($plan['price_brl'] / 100, 0, ',', '.') }}</span>
                <span class="text-gray-400 text-sm">{{ __('messages.per_month') }}</span>
            </div>
            <p class="text-green-400 text-sm mt-2">{{ __('messages.free_for_x_days', ['days' => $plan['trial_days']]) }}</p>
        </div>

        <ul class="space-y-3 mb-8">
            @foreach($plan['features'] as $feature)
            <li class="flex items-center gap-3 text-sm text-gray-300">
                <svg class="w-5 h-5 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ $feature }}
            </li>
            @endforeach
        </ul>

        <button
            wire:click="prosseguir"
            wire:loading.attr="disabled"
            class="w-full bg-green-600 hover:bg-green-500 disabled:opacity-60 text-white font-semibold py-3 rounded-lg transition-colors"
        >
            <span wire:loading.remove>{{ __('messages.start_free_trial') }}</span>
            <span wire:loading>Aguarde...</span>
        </button>

        <p class="text-center text-xs text-gray-500 mt-4">{{ __('messages.cancel_anytime') }}</p>
    </div>

    <div class="mt-6 flex items-center justify-center gap-6 text-xs text-gray-500">
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            Pagamento seguro
        </div>
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            Powered by Stripe
        </div>
    </div>
</div>
