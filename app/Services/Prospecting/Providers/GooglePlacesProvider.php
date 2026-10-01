<?php

namespace App\Services\Prospecting\Providers;

use App\Services\Costs\UsageMeter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesProvider implements PlacesProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
    ) {}

    public function name(): string
    {
        return 'google_places';
    }

    public function nearbySearch(float $lat, float $lng, int $radiusMeters, string $keyword, ?string $pageToken = null): array
    {
        $empty = ['places' => [], 'next_page_token' => null];

        if (!$this->apiKey) {
            return $empty;
        }

        try {
            $resp = Http::timeout(25)
                ->withHeaders([
                    'X-Goog-Api-Key'   => $this->apiKey,
                    'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location,places.internationalPhoneNumber,places.websiteUri,places.rating,places.userRatingCount,places.types,nextPageToken',
                ])
                ->post('https://places.googleapis.com/v1/places:searchText', array_filter([
                    'textQuery'           => $keyword,
                    // Text Search only restricts to rectangles; the caller drops the corners by distance.
                    'locationRestriction' => ['rectangle' => $this->boundingBox($lat, $lng, min($radiusMeters, 50000))],
                    'languageCode'        => 'pt-BR',
                    'pageToken'           => $pageToken,
                ]));

            if ($resp->failed()) {
                Log::warning('GooglePlacesProvider nearbySearch rejected', [
                    'keyword' => $keyword,
                    'status'  => $resp->status(),
                    'error'   => $resp->json('error.message'),
                ]);
                return $empty;
            }

            // Phone, site and rating in the field mask make every page an Enterprise request.
            app(UsageMeter::class)->places('text_search_enterprise');

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
                'page'    => $pageToken ? 'next' : 'first',
                'results' => count($out),
            ]);

            return ['places' => $out, 'next_page_token' => $resp->json('nextPageToken') ?: null];
        } catch (\Throwable $e) {
            Log::warning('GooglePlacesProvider nearbySearch failed', [
                'keyword' => $keyword,
                'error'   => $e->getMessage(),
            ]);
            return $empty;
        }
    }

    /** Rectangle enclosing the circle: 1° of latitude ≈ 111.32 km, longitude shrinks with cos(lat). */
    private function boundingBox(float $lat, float $lng, int $radiusMeters): array
    {
        $latDelta = $radiusMeters / 111_320;
        $lngDelta = $radiusMeters / (111_320 * max(0.2, cos(deg2rad($lat))));

        return [
            'low'  => ['latitude' => max(-90, $lat - $latDelta), 'longitude' => $lng - $lngDelta],
            'high' => ['latitude' => min(90, $lat + $latDelta), 'longitude' => $lng + $lngDelta],
        ];
    }
}
