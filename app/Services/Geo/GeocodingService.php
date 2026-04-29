<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    public function geocode(string $query): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $mapboxToken = (string) config('services.mapbox.token');
        if ($mapboxToken) {
            return $this->geocodeWithMapbox($query, $mapboxToken);
        }

        return $this->geocodeWithNominatim($query);
    }

    private function geocodeWithMapbox(string $query, string $token): ?array
    {
        try {
            $resp = Http::timeout(20)->get(sprintf(
                'https://api.mapbox.com/geocoding/v5/mapbox.places/%s.json',
                rawurlencode($query),
            ), [
                'access_token' => $token,
                'language' => 'pt',
                'limit' => 1,
            ]);

            $json = $resp->json();
            $feature = $json['features'][0] ?? null;
            if (!$feature) {
                return null;
            }

            $center = $feature['center'] ?? null;
            if (!is_array($center) || count($center) < 2) {
                return null;
            }

            $city = $this->extractCityFromContext($feature['context'] ?? []);

            return [
                'lat' => (float) $center[1],
                'lng' => (float) $center[0],
                'display_name' => $feature['place_name'] ?? $query,
                'city' => $city,
                'provider' => 'mapbox',
            ];
        } catch (\Throwable $e) {
            Log::warning('GeocodingService mapbox failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function geocodeWithNominatim(string $query): ?array
    {
        try {
            $resp = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'catalogo-whatsapp-ar/1.0'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'addressdetails' => 1,
                ]);

            $json = $resp->json();
            $hit = $json[0] ?? null;
            if (!$hit) {
                return null;
            }

            $addr = $hit['address'] ?? [];
            $city = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? null;

            return [
                'lat' => (float) ($hit['lat'] ?? 0),
                'lng' => (float) ($hit['lon'] ?? 0),
                'display_name' => $hit['display_name'] ?? $query,
                'city' => $city,
                'provider' => 'nominatim',
            ];
        } catch (\Throwable $e) {
            Log::warning('GeocodingService nominatim failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractCityFromContext(array $context): ?string
    {
        foreach ($context as $item) {
            $id = (string) ($item['id'] ?? '');
            if (str_starts_with($id, 'place.')) {
                return $item['text'] ?? null;
            }
        }

        foreach ($context as $item) {
            $id = (string) ($item['id'] ?? '');
            if (str_starts_with($id, 'locality.') || str_starts_with($id, 'district.')) {
                return $item['text'] ?? null;
            }
        }

        return null;
    }
}
