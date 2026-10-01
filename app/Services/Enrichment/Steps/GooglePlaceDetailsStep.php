<?php

namespace App\Services\Enrichment\Steps;

use App\Models\Lead;
use App\Services\Costs\UsageMeter;
use App\Services\Enrichment\EnrichmentContext;
use App\Services\Enrichment\EnrichmentStep;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Place Details for Google leads: opening hours, status and the reviews that show the lead's pain.
 * The search already paid for phone and site; this call adds what personalizes the message, so it
 * only runs for the top leads.
 */
class GooglePlaceDetailsStep implements EnrichmentStep
{
    private const FIELDS = 'nationalPhoneNumber,internationalPhoneNumber,websiteUri,businessStatus,regularOpeningHours,primaryType,primaryTypeDisplayName,rating,userRatingCount,reviews,editorialSummary,googleMapsUri';

    private const MAX_REVIEWS = 5;

    public function run(Lead $lead, EnrichmentContext $ctx): void
    {
        $key = (string) config('services.google_places.key');
        if ($lead->external_source !== 'google_places' || !$lead->external_id || $key === '') {
            return;
        }

        $response = Http::timeout(15)
            ->withHeaders(['X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => self::FIELDS])
            ->get('https://places.googleapis.com/v1/places/' . rawurlencode($lead->external_id), ['languageCode' => 'pt-BR']);

        if ($response->failed()) {
            Log::warning('Place Details failed', ['lead_id' => $lead->id, 'status' => $response->status(), 'error' => $response->json('error.message')]);
            return;
        }

        app(UsageMeter::class)->places('place_details_enterprise_atmosphere');

        $place = $response->json();
        $lead->business_status = $place['businessStatus'] ?? $lead->business_status;

        if ($lead->business_status === 'CLOSED_PERMANENTLY') {
            $ctx->discard('closed_permanently');
            return;
        }

        if ($phone = $place['internationalPhoneNumber'] ?? $place['nationalPhoneNumber'] ?? null) {
            $ctx->addPhone($phone, 'google_places', evidencia: 'Google Maps');
        }

        if (isset($place['regularOpeningHours'])) {
            $lead->horario_funcionamento = Arr::only($place['regularOpeningHours'], ['periods', 'weekdayDescriptions']);
        }

        $lead->website = $lead->website ?: ($place['websiteUri'] ?? null);
        $lead->ai_insights = array_merge($lead->ai_insights ?? [], array_filter([
            'rating'             => $place['rating'] ?? null,
            'user_ratings_total' => $place['userRatingCount'] ?? null,
        ], fn ($v) => $v !== null));
        $lead->dossie = array_merge($lead->dossie ?? [], array_filter([
            'segmento'    => $place['primaryTypeDisplayName']['text'] ?? null,
            'resumo'      => $place['editorialSummary']['text'] ?? null,
            'google_maps' => $place['googleMapsUri'] ?? null,
            'reviews'     => self::reviews($place['reviews'] ?? []),
        ]));
    }

    /** Rating, text and age only: the reviewer's name isn't needed (data minimization). */
    private static function reviews(array $reviews): array
    {
        return array_values(array_filter(array_map(fn (array $review) => [
            'nota'   => $review['rating'] ?? null,
            'texto'  => mb_substr(trim((string) ($review['originalText']['text'] ?? $review['text']['text'] ?? '')), 0, 500),
            'quando' => $review['relativePublishTimeDescription'] ?? null,
        ], array_slice($reviews, 0, self::MAX_REVIEWS)), fn (array $review) => $review['texto'] !== ''));
    }
}
