<?php

namespace App\Services\Enrichment;

use App\Jobs\EnrichLeadJob;
use App\Models\Lead;
use App\Models\ProspectingSearch;
use App\Services\Costs\UsageMeter;
use App\Services\Enrichment\Steps\CnpjStep;
use App\Services\Enrichment\Steps\ContactResolverStep;
use App\Services\Enrichment\Steps\GooglePlaceDetailsStep;
use App\Services\Enrichment\Steps\LineTypeStep;
use App\Services\Enrichment\Steps\WebResearchStep;
use App\Services\Enrichment\Steps\WebsiteScrapeStep;
use App\Services\Scoring\LeadScoringService;
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

    public function __construct(
        private readonly LeadScoringService $scoring,
        private readonly UsageMeter $meter,
    ) {}

    /** $searchId: the search whose batch asked for it, which pays for it (null for "Buscar contatos"). */
    public function enrich(Lead $lead, ?int $searchId = null): void
    {
        $this->meter->within(
            ['empresa_id' => $lead->empresa_id, 'prospecting_search_id' => $searchId, 'lead_id' => $lead->id, 'origem' => 'enriquecimento'],
            fn () => $this->run($lead),
        );
    }

    private function run(Lead $lead): void
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
            $this->rescore($lead);
        }

        $lead->fill(['enrichment_status' => 'done', 'enriched_at' => now()])->save();
    }

    /**
     * Reviews, site and registry data make a better judge of fit and pain than the search had, and
     * the contact confidence is now real. Done before "done" so the screen gets the new score with it.
     */
    private function rescore(Lead $lead): void
    {
        try {
            $this->scoring->evaluate($lead->empresa, [$lead]);
        } catch (\Throwable $e) {
            Log::warning('Lead rescoring after enrichment failed', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Queues the best-fit leads of a search, skipping those enriched recently. The search shows
     * "enriching" with a count that moves as each lead finishes, then "done".
     */
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
            $search->advance('done');
            return;
        }

        Lead::whereKey($leads->modelKeys())->update(['enrichment_status' => 'pending']);

        // Before dispatching: on a sync queue the batch finishes (and marks the search done) inside dispatch().
        $search->advance('enriching', ['enriquecer' => $leads->count()]);
        $searchId = $search->id;

        $batch = Bus::batch($leads->map(fn (Lead $lead) => new EnrichLeadJob($lead->id, $searchId))->all())
            ->name("enrichment:search:{$searchId}")
            ->onQueue('enrichment')
            ->allowFailures()
            // static: the callbacks are serialized with the batch, and must not carry this service along.
            ->progress(static fn () => ProspectingSearch::find($searchId)?->broadcastProgress())
            ->finally(static fn () => ProspectingSearch::find($searchId)?->advance('done'))
            ->dispatch();

        // Saved alone so a "done" written meanwhile by the batch is kept.
        $fresh = $search->fresh();
        $fresh->update(['progress' => array_merge($fresh->progress ?? [], ['batch_id' => $batch->id])]);
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
