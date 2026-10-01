<?php

namespace App\Services\Scoring;

use App\Jobs\ScoreLeadsJob;
use App\Models\Empresa;
use App\Models\Lead;
use App\Services\AIService;
use App\Services\Enrichment\ContactScorer;
use App\Services\Enrichment\EnrichmentContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Scoring v2: the list shows first who is likely to answer and to buy.
 *
 *   lead_score = 40% fit + 25% contactability + 20% pain signals + 15% proximity
 *
 * Fit and pain come from the AI (fast tier) and are kept in ai_insights; contactability is the
 * primary contact's confidence (estimated from the phone's line type until enrichment runs);
 * proximity goes from 100 at the search center to 0 at the edge of its radius. A component that is
 * still unknown counts as 0 and shows as null in ai_insights.score_breakdown.
 */
class LeadScoringService
{
    public const WEIGHTS = ['fit' => 0.40, 'contatabilidade' => 0.25, 'dor' => 0.20, 'proximidade' => 0.15];

    /** Prospect leads. Catalog leads ("internal") already showed interest and keep LeadService's distance score. */
    public const SOURCES = ['internet', 'manual'];

    private const REVIEWS = 5;

    /** "Recalcular scores": newest leads re-evaluated per click, and leads per job (one AI request). */
    private const RESCORE_LIMIT = 300;
    private const RESCORE_JOB_SIZE = 15;

    public function __construct(
        private readonly AIService $ai,
    ) {}

    /**
     * Asks the AI for fit, pain and an opening hook, then recomputes and saves lead_score. Leads of
     * a batch the AI failed on keep what an earlier evaluation found.
     *
     * @param  iterable<Lead>  $leads  all of $empresa
     */
    public function evaluate(Empresa $empresa, iterable $leads): void
    {
        $leads = Collection::make($leads)->each->setRelation('empresa', $empresa);
        if ($leads->isEmpty()) {
            return;
        }
        $leads->loadMissing('prospectingSearch:id,radius_km');

        $evaluations = collect($this->ai->avaliarLeads(
            self::profile($empresa),
            $leads->map(fn (Lead $lead) => self::facts($lead))->values()->all(),
        ))->keyBy('id');

        foreach ($leads as $lead) {
            if ($row = $evaluations->get($lead->id)) {
                $lead->ai_insights = array_merge($lead->ai_insights ?? [], [
                    'match_score'  => $row['fit'],
                    'match_motivo' => $row['motivo'],
                    'dor_score'    => $row['dor'],
                    'dor_provavel' => $row['dor_provavel'],
                    'gancho'       => $row['gancho'],
                    'avaliado_em'  => now()->toIso8601String(),
                ]);
            }

            $this->score($lead);
            $lead->save();
        }
    }

    /**
     * "Recalcular scores" (after the sales profile changes): re-evaluates the newest prospect leads
     * still in play, in the background. Null when the empresa already asked in the last 10 minutes.
     */
    public function queueRescore(Empresa $empresa): ?int
    {
        if (!Cache::add("leads:rescore:{$empresa->id}", true, now()->addMinutes(10))) {
            return null;
        }

        $ids = $empresa->leads()
            ->whereIn('source', self::SOURCES)
            ->whereNull('opted_out_at')
            ->whereNotIn('status', ['convertido', 'descartado'])
            ->latest('id')
            ->limit(self::RESCORE_LIMIT)
            ->pluck('id');

        foreach ($ids->chunk(self::RESCORE_JOB_SIZE) as $chunk) {
            ScoreLeadsJob::dispatch($empresa->id, $chunk->values()->all());
        }

        return $ids->count();
    }

    /** Recomputes lead_score and its breakdown from what the lead already has, without the AI. Doesn't save. */
    public function score(Lead $lead): void
    {
        $insights = $lead->ai_insights ?? [];

        $parts = [
            'fit'             => isset($insights['match_score']) ? (int) $insights['match_score'] : null,
            'contatabilidade' => $this->contactability($lead),
            'dor'             => isset($insights['dor_score']) ? (int) $insights['dor_score'] : null,
            'proximidade'     => $this->proximity($lead),
        ];

        $total = 0.0;
        foreach (self::WEIGHTS as $part => $weight) {
            $total += $weight * ($parts[$part] ?? 0);
        }

        $lead->lead_score = (int) round($total);
        $lead->ai_insights = array_merge($insights, [
            'score_breakdown' => $parts + ['contatabilidade_estimada' => $lead->contact_confidence === null],
        ]);
    }

    /** What the AI needs to know about the seller. Only filled fields go in the prompt. */
    public static function profile(Empresa $empresa): array
    {
        return array_filter([
            'nome'                 => $empresa->nome,
            'o_que_faz'            => $empresa->descricao_empresa,
            'oferta_principal'     => $empresa->oferta_principal,
            'problema_que_resolve' => $empresa->problema_que_resolve,
            'diferencial'          => $empresa->diferencial,
            'cliente_ideal'        => $empresa->tipo_cliente_alvo,
            'segmentos_excluidos'  => $empresa->segmentos_excluidos,
        ], fn ($value) => trim((string) $value) !== '');
    }

    /** What is known about the business, without contact data (the AI doesn't need it). */
    public static function facts(Lead $lead): array
    {
        $insights = $lead->ai_insights ?? [];
        $dossie = $lead->dossie ?? [];

        return array_filter([
            'id'              => $lead->id,
            'nome'            => $lead->nome,
            'cidade'          => $lead->cidade,
            'segmento'        => $dossie['segmento'] ?? null,
            'tipos_google'    => $insights['types'] ?? null,
            'resumo_google'   => $dossie['resumo'] ?? null,
            'nota_google'     => $insights['rating'] ?? null,
            'avaliacoes'      => $insights['user_ratings_total'] ?? null,
            'tem_site'        => !empty($lead->website),
            'porte'           => $lead->porte,
            'cnae'            => $dossie['cnae'] ?? null,
            'anos_de_mercado' => $lead->data_abertura ? (int) $lead->data_abertura->diffInYears(now()) : null,
            'sobre_o_site'    => isset($dossie['sobre']) ? mb_substr((string) $dossie['sobre'], 0, 800) : null,
            'avaliacoes_recentes' => array_map(fn (array $review) => [
                'nota'   => $review['nota'] ?? null,
                'texto'  => mb_substr((string) ($review['texto'] ?? ''), 0, 300),
                'quando' => $review['quando'] ?? null,
            ], array_slice($dossie['reviews'] ?? [], 0, self::REVIEWS)) ?: null,
        ], fn ($value) => $value !== null);
    }

    /** The primary contact's confidence; before enrichment, what the lead's own phone would get. */
    private function contactability(Lead $lead): int
    {
        if ($lead->contact_confidence !== null) {
            return $lead->contact_confidence;
        }

        if (trim((string) $lead->telefone) === '') {
            return 0;
        }

        $ctx = new EnrichmentContext($lead, $lead->empresa?->country ?? 'BR');
        $ctx->addPhone($lead->telefone, $lead->external_source === 'google_places' ? 'google_places' : 'manual');

        return ContactScorer::best($ctx, $lead->porte);
    }

    /** 100 at the center of the search (or at the empresa) down to 0 at the edge of the radius. */
    private function proximity(Lead $lead): ?int
    {
        $radiusKm = (float) ($lead->prospectingSearch?->radius_km ?: $lead->empresa?->raio_atendimento);

        if ($lead->distancia_km === null || $radiusKm <= 0) {
            return null;
        }

        return (int) round(100 * max(0, 1 - $lead->distancia_km / $radiusKm));
    }
}
