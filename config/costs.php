<?php

/*
 * List prices (USD) used to price each paid call when it happens (api_usages.custo_usd). They are
 * estimates for the admin, not the bill: free tiers, discounts and taxes are not applied. Check the
 * providers' price pages before relying on them; prices were taken on 2026-10-01.
 */
return [

    // Per million tokens. A model id matches the longest key it starts with (dated ids included).
    // cache_write is the 5-minute TTL (1.25x input); cache_read is 0.1x input.
    'anthropic' => [
        'claude-haiku-4-5'  => ['input' => 1.00, 'output' => 5.00, 'cache_read' => 0.10, 'cache_write' => 1.25],
        'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00, 'cache_read' => 0.20, 'cache_write' => 2.50],
        'claude-opus-5-5'   => ['input' => 4.00, 'output' => 20.00, 'cache_read' => 0.20, 'cache_write' => 5.00],
    ],

    // Server-side web search tool, per search.
    'anthropic_web_search' => 10.00 / 1000,

    // Google Places API (New), per request. The SKU follows the most expensive field in the mask:
    // phone/site/rating in Text Search are Enterprise; reviews/editorialSummary in Details are Atmosphere.
    'google_places' => [
        'text_search_enterprise'              => 35.00 / 1000,
        'place_details_enterprise_atmosphere' => 25.00 / 1000,
    ],

    // Twilio Lookup v2 line type intelligence, per number looked up.
    'twilio_lookup' => [
        'line_type_intelligence' => 0.008,
    ],

    // "Lead qualificado" in cost per qualified lead: AI fit at least this (same bar as the paid
    // enrichment steps) and a probable WhatsApp.
    'qualified_min_fit' => (int) env('ENRICHMENT_PAID_MIN_SCORE', 70),
];
