<?php

namespace App\Services\Enrichment\Steps;

use App\Models\Lead;
use App\Services\Enrichment\EnrichmentContext;
use App\Services\Enrichment\EnrichmentStep;
use App\Services\Enrichment\TwilioLineTypeLookup;
use App\Support\Phone;

/**
 * The local numbering plan already tells mobile from landline in Brazil. Twilio Lookup (paid) is
 * only asked about numbers it can't classify, for the good leads, when enabled.
 */
class LineTypeStep implements EnrichmentStep
{
    private const AMBIGUOUS = ['unknown', 'fixed_or_mobile'];

    public function __construct(
        private readonly TwilioLineTypeLookup $lookup,
    ) {}

    public function run(Lead $lead, EnrichmentContext $ctx): void
    {
        if (!config('twilio.lookup_enabled') || $lead->fitScore() < (int) config('services.enrichment.paid_min_score', 70)) {
            return;
        }

        foreach ($ctx->phones() as $e164) {
            if (!in_array(Phone::lineType($e164), self::AMBIGUOUS, true)) {
                continue;
            }

            if ($type = $this->lookup->lineType($e164)) {
                $ctx->lineTypes[$e164] = $type;
                $ctx->verified[$e164] = true;
            }
        }
    }
}
