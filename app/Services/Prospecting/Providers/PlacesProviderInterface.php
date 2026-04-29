<?php

namespace App\Services\Prospecting\Providers;

interface PlacesProviderInterface
{
    /**
     * @return array<int, array{
     *   external_id: string,
     *   name: string,
     *   lat: float,
     *   lng: float,
     *   address?: string|null,
     *   phone?: string|null,
     *   website?: string|null,
     *   rating?: float|null,
     *   user_ratings_total?: int|null,
     *   types?: array<int,string>|null,
     * }>
     */
    public function nearbySearch(float $lat, float $lng, int $radiusMeters, string $keyword): array;
}

