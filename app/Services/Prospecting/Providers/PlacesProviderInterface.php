<?php

namespace App\Services\Prospecting\Providers;

interface PlacesProviderInterface
{
    /** Stored in leads.external_source: place ids are only unique within a provider. */
    public function name(): string;

    /**
     * One page of places for $keyword around the point; pass the returned token to get the next one.
     *
     * @return array{
     *   places: array<int, array{
     *     external_id: string,
     *     name: string,
     *     lat: float,
     *     lng: float,
     *     address?: string|null,
     *     phone?: string|null,
     *     website?: string|null,
     *     rating?: float|null,
     *     user_ratings_total?: int|null,
     *     types?: array<int,string>|null,
     *   }>,
     *   next_page_token: string|null,
     * }
     */
    public function nearbySearch(float $lat, float $lng, int $radiusMeters, string $keyword, ?string $pageToken = null): array;
}
