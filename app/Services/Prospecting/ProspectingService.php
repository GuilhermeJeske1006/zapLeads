<?php

namespace App\Services\Prospecting;

use App\Models\Lead;
use App\Models\Empresa;
use App\Models\ProspectingSearch;
use App\Services\AIService;
use App\Services\Costs\UsageMeter;
use App\Services\Enrichment\LeadEnrichmentService;
use App\Services\Geo\Distance;
use App\Services\Geo\GeocodingService;
use App\Services\Prospecting\Providers\GooglePlacesProvider;
use App\Services\Prospecting\Providers\MapboxPlacesProvider;
use App\Services\Prospecting\Providers\PlacesProviderInterface;
use App\Services\Scoring\LeadScoringService;
use Illuminate\Support\Facades\Log;

class ProspectingService
{
    /** Google Text Search serves at most 3 pages (60 places) per query. */
    private const MAX_PAGES = 3;

    private PlacesProviderInterface $places;
    private bool $placesEnabled = false;

    public function __construct(
        private readonly AIService $ai,
        private readonly GeocodingService $geo,
        private readonly LeadEnrichmentService $enrichment,
        private readonly LeadScoringService $scoring,
        private readonly UsageMeter $meter,
    ) {
        $googleKey   = (string) config('services.google_places.key');
        $mapboxToken = (string) config('services.mapbox.token');

        if ($googleKey) {
            $this->places        = new GooglePlacesProvider($googleKey);
            $this->placesEnabled = true;
        } elseif ($mapboxToken) {
            $this->places        = new MapboxPlacesProvider($mapboxToken);
            $this->placesEnabled = true;
        } else {
            $this->places        = new MapboxPlacesProvider('');
            $this->placesEnabled = false;
        }
    }

    /**
     * Runs a search created (status "queued") before the job was dispatched, so the UI can follow
     * it by id. A job retry runs the same search again instead of creating another one.
     */
    public function run(ProspectingSearch $search, int $maxResults = 60, ?float $customLat = null, ?float $customLng = null, string $customLocationLabel = ''): ProspectingSearch
    {
        // Keywords, Places pages and ranking are charged to the search (prospecting_searches.custos).
        return $this->meter->within(
            ['empresa_id' => $search->empresa_id, 'prospecting_search_id' => $search->id, 'origem' => 'busca'],
            fn () => $this->runSearch($search, $maxResults, $customLat, $customLng, $customLocationLabel),
        );
    }

    private function runSearch(ProspectingSearch $search, int $maxResults, ?float $customLat, ?float $customLng, string $customLocationLabel): ProspectingSearch
    {
        $empresa = $search->empresa;
        $radiusKm = (float) $search->radius_km;

        if (!$this->placesEnabled) {
            return $this->fail($search, 'messages.search_error_no_provider');
        }

        if ($customLat !== null && $customLng !== null) {
            $coords = ['lat' => $customLat, 'lng' => $customLng];
            $searchCity = $this->locationLabelToCity($customLocationLabel) ?: $empresa->cidade;
        } else {
            $coords = $this->ensureStoreCoords($empresa);
            if (!$coords) {
                return $this->fail($search, 'messages.search_error_no_address');
            }
            $searchCity = $empresa->cidade;
        }

        $search->update(['status' => 'running', 'latitude' => $coords['lat'], 'longitude' => $coords['lng']]);
        $search->advance('keywords');

        $keywords = $this->ai->gerarKeywordsProspeccao($search->descricao_empresa, $search->tipo_cliente, $empresa->segmentos_excluidos);
        if (empty($keywords)) {
            $keywords = $this->fallbackKeywords($search->tipo_cliente);
        }

        $search->update(['keywords' => $keywords]);
        $search->advance('searching', ['keywords' => count($keywords)]);

        try {
            $collected = $this->collectPlaces($keywords, $coords, $radiusKm, $maxResults);

            Log::debug('ProspectingService collected places', ['count' => count($collected), 'empresa_id' => $empresa->id]);

            $leads = [];
            foreach ($collected as $p) {
                try {
                    $lead = $this->upsertLeadFromPlace($empresa, $search, $p, $radiusKm, $coords, $searchCity);
                    if ($lead) {
                        $leads[] = $lead;
                    }
                } catch (\Throwable $e) {
                    Log::warning('ProspectingService upsertLeadFromPlace failed', [
                        'place_name' => $p['name'] ?? '?',
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            Log::debug('ProspectingService leads upserted', ['count' => count($leads), 'empresa_id' => $empresa->id]);

            // Fit and pain from the AI, then lead_score. If the AI fails, leads are still scored by
            // contact and distance (and keep the fit an earlier search found).
            $search->advance('ranking', ['empresas' => count($leads)]);
            $this->scoring->evaluate($empresa, $leads);

            // Contacts, decision maker and context for the best-fit leads, in the background (stage
            // "enriching"). Queued before "done" so the results the screen loads already show them as pending.
            $this->enrichment->queueSearch($search);

            $search = $search->fresh();
            $search->update([
                'status'        => 'done',
                'results_count' => count($leads),
            ]);
            $search->broadcastProgress();

            return $search;
        } catch (\Throwable $e) {
            Log::error('ProspectingService run failed', ['empresa_id' => $empresa->id, 'error' => $e->getMessage()]);
            return $this->fail($search, 'messages.search_failed_generic');
        }
    }

    /** $error is a translation key: the screen shows it in the user's language. */
    private function fail(ProspectingSearch $search, string $error): ProspectingSearch
    {
        $search->update(['status' => 'failed', 'error' => $error]);
        $search->refreshCosts();
        $search->broadcastProgress();

        return $search;
    }

    /**
     * Up to $maxResults places inside the radius, taken in turns from each keyword's results so the
     * first keywords don't crowd out the others. Keywords with more results are paged in later rounds.
     */
    private function collectPlaces(array $keywords, array $center, float $radiusKm, int $maxResults): array
    {
        $radiusMeters = (int) round(max(1, $radiusKm) * 1000);
        $pageTokens = array_fill_keys($keywords, null);
        $collected = [];

        for ($round = 0; $round < self::MAX_PAGES && $pageTokens && count($collected) < $maxResults; $round++) {
            $pages = [];

            foreach ($pageTokens as $keyword => $token) {
                $page = $this->places->nearbySearch($center['lat'], $center['lng'], $radiusMeters, (string) $keyword, $token);
                $pages[] = $page['places'];

                if ($page['next_page_token']) {
                    $pageTokens[$keyword] = $page['next_page_token'];
                } else {
                    unset($pageTokens[$keyword]);
                }
            }

            foreach ($this->interleave($pages) as $place) {
                $externalId = (string) ($place['external_id'] ?? '');
                if ($externalId === '' || isset($collected[$externalId])) {
                    continue;
                }

                // The provider searches a rectangle; its corners are outside the radius.
                if (Distance::haversineKm($center['lat'], $center['lng'], (float) $place['lat'], (float) $place['lng']) > $radiusKm) {
                    continue;
                }

                $collected[$externalId] = $place;

                if (count($collected) >= $maxResults) {
                    break;
                }
            }
        }

        return array_values($collected);
    }

    /** [[a1, a2, a3], [b1]] → [a1, b1, a2, a3] */
    private function interleave(array $lists): array
    {
        $out = [];
        $longest = max([0, ...array_map('count', $lists)]);

        for ($i = 0; $i < $longest; $i++) {
            foreach ($lists as $list) {
                if (isset($list[$i])) {
                    $out[] = $list[$i];
                }
            }
        }

        return $out;
    }

    private function upsertLeadFromPlace(Empresa $empresa, ProspectingSearch $search, array $place, float $radiusKm, array $center, ?string $searchCity = null): ?Lead
    {
        $lat = (float) ($place['lat'] ?? 0);
        $lng = (float) ($place['lng'] ?? 0);
        if (!$lat || !$lng) {
            return null;
        }

        $distKm = Distance::haversineKm($center['lat'], $center['lng'], $lat, $lng);
        $isNearby = $distKm <= $radiusKm;

        $source = $this->places->name();
        $externalId = (string) ($place['external_id'] ?? '');
        $lead = Lead::query()
            ->where('empresa_id', $empresa->id)
            ->where('external_source', $source)
            ->where('external_id', $externalId)
            ->first();

        $payload = [
            'prospecting_search_id' => $search->id,
            'nome' => (string) ($place['name'] ?? '—'),
            'telefone' => (string) ($place['phone'] ?? ''),
            'source' => 'internet',
            'external_source' => $source,
            'external_id' => $externalId,
            'latitude' => $lat,
            'longitude' => $lng,
            'cidade' => $searchCity ?: $empresa->cidade,
            'endereco' => $place['address'] ?? null,
            'website' => $place['website'] ?? null,
            'distancia_km' => round($distKm, 2),
            'is_nearby' => $isNearby,
        ];

        $placeInsights = [
            'rating' => $place['rating'] ?? null,
            'user_ratings_total' => $place['user_ratings_total'] ?? null,
            'types' => $place['types'] ?? null,
        ];

        if ($lead) {
            // Refreshes the place data but keeps what was learned about the lead (AI insights, and the
            // primary contact chosen by enrichment, which may not be Google's number).
            if ($lead->enriched_at) {
                unset($payload['telefone']);
            }
            $lead->update($payload + ['ai_insights' => array_merge($lead->ai_insights ?? [], $placeInsights)]);
            return $lead->fresh();
        }

        return Lead::create($payload + [
            'empresa_id' => $empresa->id,
            'ai_insights' => $placeInsights,
        ]);
    }

    private function ensureStoreCoords(Empresa $empresa): ?array
    {
        if ($empresa->latitude && $empresa->longitude) {
            return ['lat' => (float) $empresa->latitude, 'lng' => (float) $empresa->longitude];
        }

        $query = trim(($empresa->endereco ?? '') . ' ' . ($empresa->cidade ?? ''));
        $hit = $this->geo->geocode($query);
        if (!$hit) {
            return null;
        }

        $empresa->update([
            'latitude' => $hit['lat'],
            'longitude' => $hit['lng'],
            'cidade' => $empresa->cidade ?: ($hit['city'] ?? null),
        ]);

        return ['lat' => (float) $hit['lat'], 'lng' => (float) $hit['lng']];
    }

    private function fallbackKeywords(string $tipoCliente): array
    {
        $tipoCliente = trim($tipoCliente);
        if ($tipoCliente === '') {
            return ['lojas', 'empresas', 'serviços'];
        }
        return array_values(array_filter(array_map('trim', preg_split('/[,;\n]/', $tipoCliente) ?: [])));
    }

    private function locationLabelToCity(string $label): ?string
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $label))));
        if (empty($parts)) {
            return null;
        }

        // Mapbox often returns: "Rua X, Bairro, Cidade - UF, Brasil"
        foreach (array_reverse($parts) as $part) {
            if (preg_match('/^(.+?)\\s*-\\s*[A-Z]{2}\\b/u', $part, $m)) {
                $candidate = trim($m[1]);
                return $candidate !== '' ? mb_substr($candidate, 0, 100) : null;
            }
        }

        // Fallback: if label looks like "Cidade/UF" or "Cidade - UF"
        $labelNorm = preg_replace('/\\s+/', ' ', $label);
        if (preg_match('/^(.+?)(?:\\s*\\/\\s*|\\s*-\\s*)([A-Z]{2})\\b/u', $labelNorm, $m)) {
            $candidate = trim($m[1]);
            return $candidate !== '' ? mb_substr($candidate, 0, 100) : null;
        }

        return null;
    }
}
