<?php

namespace App\Services\Enrichment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/** Twilio Lookup v2 line type intelligence: paid per query, so cached for 30 days per number. */
class TwilioLineTypeLookup
{
    /** @return 'mobile'|'fixed'|'voip'|'toll_free'|null null when unknown or the lookup failed */
    public function lineType(string $e164): ?string
    {
        $key = "lookup:line_type:{$e164}";
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $type = $this->fetch($e164);
        if ($type !== null) {
            Cache::put($key, $type, now()->addDays(30));
        }

        return $type;
    }

    protected function fetch(string $e164): ?string
    {
        try {
            $result = (new Client(config('twilio.sid'), config('twilio.token')))
                ->lookups->v2->phoneNumbers($e164)
                ->fetch(['fields' => 'line_type_intelligence']);
        } catch (\Throwable $e) {
            Log::warning('Twilio Lookup failed', ['phone' => $e164, 'error' => $e->getMessage()]);
            return null;
        }

        return match ($result->lineTypeIntelligence['type'] ?? null) {
            'mobile'                    => 'mobile',
            'landline'                  => 'fixed',
            'fixedVoip', 'nonFixedVoip' => 'voip',
            'tollFree'                  => 'toll_free',
            default                     => null,
        };
    }
}
