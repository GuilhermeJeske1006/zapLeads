<?php

return [
    'sid'   => env('TWILIO_SID'),
    'token' => env('TWILIO_AUTH_TOKEN'),

    // Validate X-Twilio-Signature on inbound webhooks. Can only be disabled outside production.
    'webhook_validate' => env('TWILIO_WEBHOOK_VALIDATE', true),

    // Twilio Lookup line type intelligence for numbers the numbering plan can't classify (paid per query).
    'lookup_enabled' => env('TWILIO_LOOKUP_ENABLED', false),
];
