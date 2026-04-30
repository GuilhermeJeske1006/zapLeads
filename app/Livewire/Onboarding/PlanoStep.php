<?php

namespace App\Livewire\Onboarding;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.onboarding-layout', ['step' => 2])]
class PlanoStep extends Component
{
    public function prosseguir(): void
    {
        $this->redirect(route('onboarding.pagamento'), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding.plano-step', [
            'plan' => config('plans'),
        ]);
    }
}
