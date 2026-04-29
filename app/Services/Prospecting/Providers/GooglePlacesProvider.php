<?php

namespace App\Services\Prospecting\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesProvider implements PlacesProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
    ) {}

    public function nearbySearch(float $lat, float $lng, int $radiusMeters, string $keyword): array
    {
        if (!$this->apiKey) {
            return [];
        }

        try {
            $resp = Http::timeout(25)
                ->withHeaders([
                    'X-Goog-Api-Key'   => $this->apiKey,
                    'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location,places.internationalPhoneNumber,places.websiteUri,places.rating,places.userRatingCount,places.types',
                ])
                ->post('https://places.googleapis.com/v1/places:searchText', [
                    'textQuery'    => $keyword,
                    'locationBias' => [
                        'circle' => [
                            'center' => ['latitude' => $lat, 'longitude' => $lng],
                            'radius' => (float) min($radiusMeters, 50000),
                        ],
                    ],
                    'maxResultCount' => 20,
                    'languageCode'   => 'pt-BR',
                ]);

            $places = $resp->json('places') ?? [];
            $out    = [];

            foreach ($places as $p) {
                $placeId = (string) ($p['id'] ?? '');
                $locLat  = (float) ($p['location']['latitude']  ?? 0);
                $locLng  = (float) ($p['location']['longitude'] ?? 0);

                if (!$placeId || !$locLat || !$locLng) {
                    continue;
                }

                $out[] = [
                    'external_id'        => $placeId,
                    'name'               => (string) ($p['displayName']['text'] ?? '—'),
                    'lat'                => $locLat,
                    'lng'                => $locLng,
                    'address'            => $p['formattedAddress'] ?? null,
                    'phone'              => $p['internationalPhoneNumber'] ?? null,
                    'website'            => $p['websiteUri'] ?? null,
                    'rating'             => isset($p['rating']) ? (float) $p['rating'] : null,
                    'user_ratings_total' => isset($p['userRatingCount']) ? (int) $p['userRatingCount'] : null,
                    'types'              => $p['types'] ?? null,
                ];
            }

            Log::debug('GooglePlacesProvider nearbySearch', [
                'keyword' => $keyword,
                'results' => count($out),
            ]);

            return $out;
        } catch (\Throwable $e) {
            Log::warning('GooglePlacesProvider nearbySearch failed', [
                'keyword' => $keyword,
                'error'   => $e->getMessage(),
            ]);
            return [];
        }
    }
}
