<?php

namespace App\Services\Enrichment;

use App\Jobs\EnrichLeadJob;
use App\Models\Lead;
use App\Models\ProspectingSearch;
use App\Services\Enrichment\Steps\CnpjStep;
use App\Services\Enrichment\Steps\ContactResolverStep;
use App\Services\Enrichment\Steps\GooglePlaceDetailsStep;
use App\Services\Enrichment\Steps\LineTypeStep;
use App\Services\Enrichment\Steps\WebResearchStep;
use App\Services\Enrichment\Steps\WebsiteScrapeStep;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Finds the best WhatsApp for a lead, who decides there, and context to personalize the message,
 * by running the steps as a cascade. A step that fails is skipped; the others still run.
 */
class LeadEnrichmentService
{
    /** In order: each step can use what the previous ones found (the site gives the CNPJ, etc.). */
    private const STEPS = [
        GooglePlaceDetailsStep::class,
        WebsiteScrapeStep::class,
        CnpjStep::class,
        WebResearchStep::class,
        LineTypeStep::class,
    ];

    /** A lead enriched less than this long ago is not paid for again. */
    private const FRESH_DAYS = 30;

    public function enrich(Lead $lead): void
    {
        $lead->update(['enrichment_status' => 'running']);

        $ctx = new EnrichmentContext($lead, $lead->empresa?->country ?? 'BR');
        $this->seed($lead, $ctx);

        foreach (self::STEPS as $step) {
            if ($ctx->discarded !== null) {
                break;
            }

            try {
                app($step)->run($lead, $ctx);
            } catch (\Throwable $e) {
                Log::warning('Enrichment step failed', ['lead_id' => $lead->id, 'step' => class_basename($step), 'error' => $e->getMessage()]);
            }
        }

        if ($ctx->discarded !== null) {
            $lead->dossie = array_merge($lead->dossie ?? [], ['descartado' => $ctx->discarded]);
            if (($lead->status ?? 'novo') === 'novo') {
                $lead->status = 'descartado';
            }
        } else {
            app(ContactResolverStep::class)->run($lead, $ctx);
        }

        $lead->fill(['enrichment_status' => 'done', 'enriched_at' => now()])->save();
    }

    /** Queues the best-fit leads of a search, skipping those enriched recently. */
    public function queueSearch(ProspectingSearch $search): void
    {
        $leads = Lead::where('empresa_id', $search->empresa_id)
            ->where('prospecting_search_id', $search->id)
            ->where(fn ($q) => $q->whereNull('enriched_at')->orWhere('enriched_at', '<', now()->subDays(self::FRESH_DAYS)))
            ->get()
            ->sortByDesc(fn (Lead $lead) => $lead->fitScore())
            ->take((int) config('services.enrichment.top_n', 30))
            ->values();

        if ($leads->isEmpty()) {
            return;
        }

        Lead::whereKey($leads->modelKeys())->update(['enrichment_status' => 'pending']);

        Bus::batch($leads->map(fn (Lead $lead) => new EnrichLeadJob($lead->id))->all())
            ->name("enrichment:search:{$search->id}")
            ->onQueue('enrichment')
            ->allowFailures()
            ->dispatch();
    }

    /** One lead, on demand ("Buscar contatos"). */
    public function queue(Lead $lead): void
    {
        $lead->update(['enrichment_status' => 'pending']);

        EnrichLeadJob::dispatch($lead->id);
    }

    /**
     * What the lead already has. The first run takes its phone (from the search or typed by hand);
     * later runs keep the contacts typed by hand, the steps bring back the rest.
     */
    private function seed(Lead $lead, EnrichmentContext $ctx): void
    {
        $contacts = $lead->contacts()->get();

        if ($contacts->isEmpty()) {
            if (trim((string) $lead->telefone) !== '') {
                $origem = $lead->external_source === 'google_places' ? 'google_places' : 'manual';
                $ctx->addPhone($lead->telefone, $origem, evidencia: $origem === 'google_places' ? 'Google Maps' : null);
            }
            return;
        }

        foreach ($contacts->where('origem', 'manual') as $contact) {
            $contact->isPhone()
                ? $ctx->addPhone($contact->valor, 'manual', whatsapp: $contact->tipo === 'whatsapp')
                : $ctx->add($contact->tipo, $contact->valor, 'manual');
        }
    }
}
