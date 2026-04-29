<?php

return [
    'sid'          => env('TWILIO_SID'),
    'token'        => env('TWILIO_AUTH_TOKEN'),
    'from'         => env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'),
];
