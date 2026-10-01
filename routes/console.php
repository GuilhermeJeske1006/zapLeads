<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Channel quality (Meta, through Twilio) and opt-out rate: pauses prospecting on numbers at risk.
Schedule::command('whatsapp:check-channels')->hourly()->withoutOverlapping();
