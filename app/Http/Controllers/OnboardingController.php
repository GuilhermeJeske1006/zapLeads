<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;

class OnboardingController extends Controller
{
    public function empresa()
    {
        return view('onboarding.empresa');
    }

    public function plano()
    {
        $user = auth()->user();

        if (! $user->empresa || ! $user->empresa->nome) {
            return redirect()->route('onboarding.empresa');
        }

        return view('onboarding.plano', [
            'plan' => config('plans'),
        ]);
    }

    public function pagamento()
    {
        $user = auth()->user();

        if (! $user->empresa || ! $user->empresa->nome) {
            return redirect()->route('onboarding.empresa');
        }

        $intent = $user->createSetupIntent();

        return view('onboarding.pagamento', [
            'intent' => $intent,
            'plan' => config('plans'),
            'stripeKey' => config('cashier.key'),
        ]);
    }

    public function processarPagamento(Request $request)
    {
        $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        $user = auth()->user();
        $priceId = config('plans.stripe_price_id');
        $trialDays = config('plans.trial_days', 14);

        try {
            $user->newSubscription('default', $priceId)
                ->trialDays($trialDays)
                ->create($request->payment_method);
        } catch (IncompletePayment $e) {
            return redirect()->route('cashier.payment', [$e->payment->id, 'redirect' => route('onboarding.pagamento')]);
        } catch (\Exception $e) {
            Log::error('Subscription error: ' . $e->getMessage());
            return back()->withErrors(['payment_method' => 'Erro ao processar pagamento. Tente novamente.']);
        }

        $user->update(['onboarding_completed_at' => now()]);

        return redirect()->route('dashboard')->with('success', 'Bem-vindo! Seu período de teste começou.');
    }
}
