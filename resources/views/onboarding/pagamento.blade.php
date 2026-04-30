<x-onboarding-layout :step="3">
    @push('head')
    <script src="https://js.stripe.com/v3/"></script>
    @endpush

    <div>
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-white mb-2">Dados de pagamento</h2>
            <p class="text-gray-400 text-sm">
                Seu trial de {{ $plan['trial_days'] }} dias começa agora. Você só será cobrado após o período gratuito.
            </p>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-8">

            {{-- Plan Summary --}}
            <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-xl mb-6 border border-gray-700">
                <div>
                    <p class="text-sm font-medium text-white">{{ $plan['name'] }}</p>
                    <p class="text-xs text-green-400 mt-0.5">{{ $plan['trial_days'] }} dias grátis</p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold text-white">R$ {{ number_format($plan['price_brl'] / 100, 2, ',', '.') }}/mês</p>
                    <p class="text-xs text-gray-500">após o trial</p>
                </div>
            </div>

            @if ($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 mb-5">
                <p class="text-red-400 text-sm">{{ $errors->first() }}</p>
            </div>
            @endif

            <form id="payment-form" method="POST" action="{{ route('onboarding.pagamento.processar') }}">
                @csrf
                <input type="hidden" name="payment_method" id="payment-method-input">

                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Dados do cartão</label>
                    <div
                        id="payment-element"
                        class="bg-gray-800 border border-gray-700 rounded-lg p-4 min-h-[44px]"
                    ></div>
                    <div id="payment-errors" class="text-red-400 text-xs mt-2 hidden"></div>
                </div>

                <button
                    id="submit-btn"
                    type="submit"
                    class="w-full bg-green-600 hover:bg-green-500 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold py-3 rounded-lg transition-colors"
                >
                    <span id="btn-text">Começar período gratuito →</span>
                    <span id="btn-loading" class="hidden">Processando...</span>
                </button>

                <p class="text-center text-xs text-gray-500 mt-4">
                    Cobraremos R$ {{ number_format($plan['price_brl'] / 100, 2, ',', '.') }} após {{ $plan['trial_days'] }} dias. Cancele antes sem custo.
                </p>
            </form>
        </div>

        <div class="mt-6 flex items-center justify-center gap-6 text-xs text-gray-500">
            <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                SSL 256-bit
            </div>
            <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Powered by Stripe
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const stripe = Stripe('{{ $stripeKey }}');
        const elements = stripe.elements({
            clientSecret: '{{ $intent->client_secret }}',
            appearance: {
                theme: 'night',
                variables: {
                    colorPrimary: '#22c55e',
                    colorBackground: '#1f2937',
                    colorText: '#f3f4f6',
                    colorDanger: '#ef4444',
                    fontFamily: 'Inter, sans-serif',
                    borderRadius: '8px',
                },
            },
        });

        const paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        const form = document.getElementById('payment-form');
        const submitBtn = document.getElementById('submit-btn');
        const btnText = document.getElementById('btn-text');
        const btnLoading = document.getElementById('btn-loading');
        const errorsDiv = document.getElementById('payment-errors');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            submitBtn.disabled = true;
            btnText.classList.add('hidden');
            btnLoading.classList.remove('hidden');
            errorsDiv.classList.add('hidden');

            const { setupIntent, error } = await stripe.confirmSetup({
                elements,
                redirect: 'if_required',
            });

            if (error) {
                errorsDiv.textContent = error.message;
                errorsDiv.classList.remove('hidden');
                submitBtn.disabled = false;
                btnText.classList.remove('hidden');
                btnLoading.classList.add('hidden');
                return;
            }

            document.getElementById('payment-method-input').value = setupIntent.payment_method;
            form.submit();
        });
    </script>
    @endpush
</x-onboarding-layout>
