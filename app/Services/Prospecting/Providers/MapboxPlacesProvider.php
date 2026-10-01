<?php

namespace App\Services\Prospecting\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MapboxPlacesProvider implements PlacesProviderInterface
{
    public function __construct(
        private readonly string $token,
    ) {}

    public function name(): string
    {
        return 'mapbox';
    }

    /** The geocoding API has no paging: everything comes in the first page. */
    public function nearbySearch(float $lat, float $lng, int $radiusMeters, string $keyword, ?string $pageToken = null): array
    {
        if (!$this->token || $pageToken !== null) {
            return ['places' => [], 'next_page_token' => null];
        }

        $bbox = $this->bboxFromRadius($lat, $lng, $radiusMeters);

        try {
            $resp = Http::timeout(25)->get(sprintf(
                'https://api.mapbox.com/geocoding/v5/mapbox.places/%s.json',
                rawurlencode($keyword)
            ), [
                'access_token' => $this->token,
                'language' => 'pt',
                'types' => 'poi',
                'proximity' => "{$lng},{$lat}",
                'bbox' => implode(',', $bbox),
                'limit' => 25,
            ]);

            $json = $resp->json();

            $out = [];
            foreach (($json['features'] ?? []) as $f) {
                $id = (string) ($f['id'] ?? '');
                $center = $f['center'] ?? null;
                if (!$id || !is_array($center) || count($center) < 2) {
                    continue;
                }

                $out[] = [
                    'external_id' => $id,
                    'name' => (string) ($f['text'] ?? ($f['place_name'] ?? '—')),
                    'lat' => (float) $center[1],
                    'lng' => (float) $center[0],
                    'address' => $f['place_name'] ?? null,
                    'phone' => null,
                    'website' => null,
                    'rating' => null,
                    'user_ratings_total' => null,
                    'types' => $f['properties']['category'] ?? null,
                ];
            }

            return ['places' => $out, 'next_page_token' => null];
        } catch (\Throwable $e) {
            Log::warning('MapboxPlacesProvider nearbySearch failed', ['keyword' => $keyword, 'error' => $e->getMessage()]);
            return ['places' => [], 'next_page_token' => null];
        }
    }

    /**
     * Returns bbox as [minLng, minLat, maxLng, maxLat]
     */
    private function bboxFromRadius(float $lat, float $lng, int $radiusMeters): array
    {
        $radiusKm = max(1, $radiusMeters) / 1000.0;

        // Rough conversion: 1 deg lat ~ 110.574km, 1 deg lng ~ 111.320*cos(lat) km
        $latDelta = $radiusKm / 110.574;
        $lngDelta = $radiusKm / (111.320 * max(0.2, cos(deg2rad($lat))));

        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;

        return [$minLng, $minLat, $maxLng, $maxLat];
    }
}

