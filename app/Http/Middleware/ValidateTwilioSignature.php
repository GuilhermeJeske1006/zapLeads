<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

/**
 * Rejects webhook calls not signed by Twilio with our auth token. Without it anyone could post
 * fake inbound messages: create conversations, force opt-outs or make the bot reply from a
 * tenant's number to an arbitrary phone.
 */
class ValidateTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('twilio.webhook_validate') && !app()->isProduction()) {
            return $next($request);
        }

        $token     = (string) config('twilio.token');
        $signature = (string) $request->header('X-Twilio-Signature', '');

        // Twilio signs the exact URL it called; trustProxies makes scheme/host match the public URL.
        $url = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        if ($token === '' || $signature === ''
            || !(new RequestValidator($token))->validate($signature, $url, $request->request->all())) {
            Log::warning('Twilio webhook rejected: invalid signature', ['url' => $url, 'ip' => $request->ip()]);
            abort(403);
        }

        return $next($request);
    }
}
