<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private array $supported = ['pt_BR', 'es'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = Session::get('locale')
            ?? $this->detectFromBrowser($request)
            ?? 'pt_BR';

        if (!in_array($locale, $this->supported)) {
            $locale = 'pt_BR';
        }

        App::setLocale($locale);

        return $next($request);
    }

    private function detectFromBrowser(Request $request): ?string
    {
        $browserLocale = $request->getPreferredLanguage(['pt_BR', 'pt', 'es']);

        return match (true) {
            str_starts_with($browserLocale, 'es') => 'es',
            str_starts_with($browserLocale, 'pt') => 'pt_BR',
            default => null,
        };
    }
}
