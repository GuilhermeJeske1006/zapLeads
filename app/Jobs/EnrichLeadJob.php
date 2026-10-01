<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Enrichment\LeadEnrichmentService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/** One lead through the enrichment cascade, on the "enrichment" queue. */
class EnrichLeadJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;
    public int $backoff = 30;
    // Up to 5 page reads of 8 s plus Place Details, CNPJ and an optional web search.
    public int $timeout = 120;

    public function __construct(
        public readonly int $leadId,
        public readonly ?int $searchId = null,
    ) {
        $this->onQueue('enrichment');
    }

    public function handle(LeadEnrichmentService $enrichment): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($lead = Lead::find($this->leadId)) {
            $enrichment->enrich($lead, $this->searchId);
        }
    }

    public function failed(?Throwable $e): void
    {
        Lead::whereKey($this->leadId)->update(['enrichment_status' => 'failed']);
    }
}
