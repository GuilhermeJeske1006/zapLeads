<?php

namespace App\Services\Prospecting;

use App\Models\Lead;
use App\Models\Loja;
use App\Models\ProspectingSearch;
use App\Services\AIService;
use App\Services\Geo\Distance;
use App\Services\Geo\GeocodingService;
use App\Services\Prospecting\Providers\GooglePlacesProvider;
use App\Services\Prospecting\Providers\MapboxPlacesProvider;
use App\Services\Prospecting\Providers\PlacesProviderInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ProspectingService
{
    private PlacesProviderInterface $places;
    private bool $placesEnabled = false;

    public function __construct(
        private readonly AIService $ai,
        private readonly GeocodingService $geo,
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

    public function run(Loja $loja, string $descricaoEmpresa, string $tipoCliente, float $radiusKm, int $maxResults = 60): ProspectingSearch
    {
        if (!$this->placesEnabled) {
            return ProspectingSearch::create([
                'loja_id' => $loja->id,
                'descricao_empresa' => $descricaoEmpresa,
                'tipo_cliente' => $tipoCliente,
                'latitude' => (float) ($loja->latitude ?? 0),
                'longitude' => (float) ($loja->longitude ?? 0),
                'radius_km' => $radiusKm,
                'keywords' => [],
                'status' => 'failed',
                'error' => 'Configuração ausente: defina GOOGLE_PLACES_API_KEY ou MAPBOX_TOKEN no .env.',
            ]);
        }

        $coords = $this->ensureStoreCoords($loja);
        if (!$coords) {
            return ProspectingSearch::create([
                'loja_id' => $loja->id,
                'descricao_empresa' => $descricaoEmpresa,
                'tipo_cliente' => $tipoCliente,
                'latitude' => 0,
                'longitude' => 0,
                'radius_km' => $radiusKm,
                'keywords' => [],
                'status' => 'failed',
                'error' => 'Não foi possível localizar o endereço da empresa. Atualize o endereço e tente novamente.',
            ]);
        }

        $keywords = $this->ai->gerarKeywordsProspeccao($descricaoEmpresa, $tipoCliente);
        if (empty($keywords)) {
            $keywords = $this->fallbackKeywords($tipoCliente);
        }

        $search = ProspectingSearch::create([
            'loja_id' => $loja->id,
            'descricao_empresa' => $descricaoEmpresa,
            'tipo_cliente' => $tipoCliente,
            'latitude' => $coords['lat'],
            'longitude' => $coords['lng'],
            'radius_km' => $radiusKm,
            'keywords' => $keywords,
            'status' => 'running',
        ]);

        try {
            $radiusMeters = (int) round(max(1, $radiusKm) * 1000);
            $collected = [];

            foreach ($keywords as $keyword) {
                if (count($collected) >= $maxResults) {
                    break;
                }

                $places = $this->places->nearbySearch($coords['lat'], $coords['lng'], $radiusMeters, (string) $keyword);
                foreach ($places as $p) {
                    if (count($collected) >= $maxResults) {
                        break;
                    }

                    $externalId = (string) ($p['external_id'] ?? '');
                    if ($externalId === '') {
                        continue;
                    }

                    $collected[$externalId] = $p;
                }
            }

            Log::debug('ProspectingService collected places', ['count' => count($collected), 'loja_id' => $loja->id]);

            $leads = [];
            foreach (array_values($collected) as $p) {
                try {
                    $lead = $this->upsertLeadFromPlace($loja, $search, $p, $radiusKm);
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

            Log::debug('ProspectingService leads upserted', ['count' => count($leads), 'loja_id' => $loja->id]);

            // AI ranking — optional, non-blocking. Falls back to raw results if AI fails.
            $ranked = $this->ai->buscarLeadsPorPerfil(
                $descricaoEmpresa,
                $tipoCliente,
                array_map(fn (Lead $l) => $l->toArray(), $leads),
            );

            Log::debug('ProspectingService AI ranked', ['ranked' => count($ranked), 'total' => count($leads)]);

            if (!empty($ranked)) {
                $rankedById = collect($ranked)->keyBy('id');
                foreach ($leads as $lead) {
                    $match = $rankedById->get($lead->id);
                    if (!$match) {
                        continue;
                    }
                    $lead->ai_insights = array_merge($lead->ai_insights ?? [], Arr::only($match, ['match_score', 'match_motivo']));
                    $lead->save();
                }
            }

            $search->update([
                'status'        => 'done',
                'results_count' => count($leads),
            ]);

            return $search->fresh(['leads']);
        } catch (\Throwable $e) {
            Log::error('ProspectingService run failed', ['loja_id' => $loja->id, 'error' => $e->getMessage()]);
            $search->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            return $search->fresh();
        }
    }

    private function upsertLeadFromPlace(Loja $loja, ProspectingSearch $search, array $place, float $radiusKm): ?Lead
    {
        $lat = (float) ($place['lat'] ?? 0);
        $lng = (float) ($place['lng'] ?? 0);
        if (!$lat || !$lng) {
            return null;
        }

        $distKm = Distance::haversineKm((float) $loja->latitude, (float) $loja->longitude, $lat, $lng);
        $isNearby = $distKm <= $radiusKm;

        $externalId = (string) ($place['external_id'] ?? '');
        $lead = Lead::query()
            ->where('loja_id', $loja->id)
            ->where('external_source', 'mapbox')
            ->where('external_id', $externalId)
            ->first();

        $payload = [
            'prospecting_search_id' => $search->id,
            'nome' => (string) ($place['name'] ?? '—'),
            'telefone' => (string) ($place['phone'] ?? ''),
            'source' => 'internet',
            'external_source' => 'mapbox',
            'external_id' => $externalId,
            'latitude' => $lat,
            'longitude' => $lng,
            'cidade' => $loja->cidade,
            'endereco' => $place['address'] ?? null,
            'website' => $place['website'] ?? null,
            'distancia_km' => round($distKm, 2),
            'is_nearby' => $isNearby,
            'lead_score' => 50,
            'ai_insights' => [
                'rating' => $place['rating'] ?? null,
                'user_ratings_total' => $place['user_ratings_total'] ?? null,
                'types' => $place['types'] ?? null,
            ],
        ];

        if ($lead) {
            $lead->update($payload);
            return $lead->fresh();
        }

        $payload['loja_id'] = $loja->id;
        return Lead::create($payload);
    }

    private function ensureStoreCoords(Loja $loja): ?array
    {
        if ($loja->latitude && $loja->longitude) {
            return ['lat' => (float) $loja->latitude, 'lng' => (float) $loja->longitude];
        }

        $query = trim(($loja->endereco ?? '') . ' ' . ($loja->cidade ?? ''));
        $hit = $this->geo->geocode($query);
        if (!$hit) {
            return null;
        }

        $loja->update([
            'latitude' => $hit['lat'],
            'longitude' => $hit['lng'],
            'cidade' => $loja->cidade ?: ($hit['city'] ?? null),
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
}
