<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'models' => [
            'fast'    => env('ANTHROPIC_MODEL_FAST', 'claude-haiku-4-5-20251001'),   // keywords, ranking, classificação
            'quality' => env('ANTHROPIC_MODEL_QUALITY', 'claude-sonnet-5-5'),        // mensagens, dossiê
        ],
    ],

    'google_places' => [
        'key' => env('GOOGLE_PLACES_API_KEY'),
    ],

    'enrichment' => [
        'top_n'                 => (int) env('ENRICHMENT_TOP_N', 30),        // leads enriched per search, best fit first
        'paid_min_score'        => (int) env('ENRICHMENT_PAID_MIN_SCORE', 70), // fit needed for paid steps (web search, Lookup)
        'web_research'          => (bool) env('ENRICHMENT_WEB_RESEARCH', false),
        'web_research_max_uses' => (int) env('ENRICHMENT_WEB_RESEARCH_MAX_USES', 3),
    ],

    'cnpj' => [
        // Tried in order: brasilapi, minhareceita.
        'providers' => explode(',', env('CNPJ_PROVIDERS', 'brasilapi,minhareceita')),
    ],

    'mapbox' => [
        'token' => env('MAPBOX_TOKEN'),
        'style' => env('MAPBOX_STYLE', 'mapbox/streets-v12'),
    ],

];
