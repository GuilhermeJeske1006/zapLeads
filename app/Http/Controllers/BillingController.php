<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $plan = config('plans');
        $subscription = $user?->subscription('default');

        $subscriptionData = null;
        if ($subscription) {
            $subscriptionData = [
                'stripe_status' => $subscription->stripe_status,
                'stripe_price' => $subscription->stripe_price,
                'trial_ends_at' => $subscription->trial_ends_at,
                'ends_at' => $subscription->ends_at,
                'created_at' => $subscription->created_at,
            ];
        }

        $invoices = [];
        try {
            // Pulls from Stripe API (Cashier). If Stripe keys are missing, it may throw.
            $invoices = $user
                ? $user->invoices()->filter(fn ($inv) => $inv->rawTotal() > 0)
                : [];
        } catch (\Throwable $e) {
            Log::warning('Billing invoices fetch failed', ['error' => $e->getMessage()]);
            $invoices = [];
        }

        return view('billing.index', [
            'plan' => $plan,
            'user' => $user,
            'subscription' => $subscriptionData,
            'invoices' => $invoices,
        ]);
    }

    public function cancel(): RedirectResponse
    {
        $user = auth()->user();
        $subscription = $user?->subscription('default');

        if (! $subscription) {
            return back()->with('success', 'Nenhuma assinatura encontrada.');
        }

        if ($subscription->ended()) {
            return back()->with('success', 'Assinatura já está cancelada.');
        }

        $subscription->cancel();

        return back()->with('success', 'Assinatura cancelada. Você manterá acesso até o fim do período atual.');
    }
}
