<?php

namespace App\Services\Enrichment;

use App\Models\Lead;

interface EnrichmentStep
{
    public function run(Lead $lead, EnrichmentContext $ctx): void;
}
