<?php

return [
    'trial_days' => env('PLAN_TRIAL_DAYS', 14),

    'stripe_price_id'     => env('STRIPE_PRICE_ID'),
    'stripe_price_id_ars' => env('STRIPE_PRICE_ID_ARS'),

    'price_brl' => env('PLAN_PRICE_BRL', 9700),   // centavos BRL
    'price_ars' => env('PLAN_PRICE_ARS', 999700),  // centavos ARS

    'name' => 'Plano Pro',

    'features' => [
        'Catálogo digital ilimitado',
        'Bot WhatsApp automático',
        'Gestão de leads',
        'Campanhas e sequências',
        'IA integrada (Claude)',
        'Suporte via WhatsApp',
    ],
];
