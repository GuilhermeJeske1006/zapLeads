<?php

namespace App\Services\Enrichment\Steps;

use App\Models\Lead;
use App\Services\AIService;
use App\Services\Enrichment\ContactScorer;
use App\Services\Enrichment\EnrichmentContext;
use App\Services\Enrichment\EnrichmentStep;
use App\Services\Enrichment\WebsiteContactExtractor;

/**
 * Paid web search (US$ 10 per 1,000 searches plus tokens), only for good leads still without a
 * reliable WhatsApp and only when enabled. Every fact needs a source URL the search actually
 * returned; Instagram and Facebook are never scraped.
 */
class WebResearchStep implements EnrichmentStep
{
    /** A number this good needs no research. */
    private const RELIABLE = 70;

    public function __construct(
        private readonly AIService $ai,
    ) {}

    public function run(Lead $lead, EnrichmentContext $ctx): void
    {
        if (!config('services.enrichment.web_research')
            || $lead->fitScore() < (int) config('services.enrichment.paid_min_score', 70)
            || ContactScorer::best($ctx, $lead->porte) >= self::RELIABLE) {
            return;
        }

        $research = $this->ai->pesquisarContatosNaWeb($lead->nome, $lead->cidade, $lead->website);
        if ($research === null) {
            return;
        }

        $sources = array_map(fn (string $url) => rtrim($url, '/'), $research['urls']);

        foreach ($this->ai->extrairContatosDaPesquisa($research['texto'], $research['urls']) as $item) {
            $url = rtrim(trim($item['evidencia_url']), '/');
            if (!in_array($url, $sources, true)) {
                continue; // a source the search never returned
            }

            $valor = trim($item['valor']);
            match ($item['tipo']) {
                'whatsapp'     => $ctx->addPhone($valor, 'web_research', whatsapp: true, evidencia: mb_substr($url, 0, 255)),
                'telefone'     => $ctx->addPhone($valor, 'web_research', evidencia: mb_substr($url, 0, 255)),
                'email'        => filter_var($valor, FILTER_VALIDATE_EMAIL) ? $ctx->add('email', strtolower($valor), 'web_research', $url) : null,
                'instagram'    => ($handle = WebsiteContactExtractor::instagramHandle($valor)) ? $ctx->add('instagram', $handle, 'web_research', $url) : null,
                'proprietario' => $this->owner($lead, $valor, $url),
                default        => null,
            };
        }

        $lead->dossie = array_merge($lead->dossie ?? [], [
            'pesquisa_web' => ['resumo' => mb_substr($research['texto'], 0, 1500), 'fontes' => array_slice($research['urls'], 0, 10)],
        ]);
    }

    /** The registry (CnpjStep) is a better source; the web only fills the gap. */
    private function owner(Lead $lead, string $nome, string $url): void
    {
        if (!$lead->decisor_nome) {
            $lead->decisor_nome = mb_substr($nome, 0, 120);
            $lead->decisor_cargo = 'Proprietário (fonte: web)';
            $lead->dossie = array_merge($lead->dossie ?? [], ['decisor_fonte' => $url]);
        }
    }
}
