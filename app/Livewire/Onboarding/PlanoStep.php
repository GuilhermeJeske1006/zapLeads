<?php

namespace App\Livewire\Onboarding;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.onboarding-layout', ['step' => 3])]
class PlanoStep extends Component
{
    public function prosseguir(): void
    {
        $this->redirect(route('onboarding.pagamento'), navigate: true);
    }

    public function render()
    {
        $empresa = auth()->user()->empresa;
        $currency = $empresa?->currency ?? 'BRL';
        $plan = config('plans');

        $plan['currency']        = $currency;
        $plan['currency_symbol'] = $empresa?->currencySymbol() ?? 'R$';
        $plan['price_display']   = $currency === 'ARS' ? $plan['price_ars'] : $plan['price_brl'];
        $plan['stripe_price']    = $currency === 'ARS'
            ? ($plan['stripe_price_id_ars'] ?? $plan['stripe_price_id'])
            : $plan['stripe_price_id'];

        return view('livewire.onboarding.plano-step', compact('plan'));
    }
}
