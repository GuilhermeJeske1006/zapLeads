<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (method_exists($user, 'isMasterAdmin') && $user->isMasterAdmin()) {
            return $next($request);
        }

        if ($user->onboarding_completed_at || $user->subscribed('default')) {
            return $next($request);
        }

        $empresa = $user->empresa;

        if (! $empresa || ! $empresa->nome) {
            return redirect()->route('onboarding.empresa');
        }

        return redirect()->route('onboarding.plano');
    }
}
