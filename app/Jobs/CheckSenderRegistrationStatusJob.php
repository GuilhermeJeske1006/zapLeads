<?php

namespace App\Jobs;

use App\Models\WhatsAppSenderRegistration;
use App\Services\TwilioSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckSenderRegistrationStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly WhatsAppSenderRegistration $registration
    ) {}

    public function handle(TwilioSenderService $service): void
    {
        $reg = $this->registration->fresh();

        if (!$reg || !in_array($reg->status, [
            WhatsAppSenderRegistration::STATUS_PENDING_APPROVAL,
        ])) {
            return;
        }

        $status = $service->syncStatus($reg);

        Log::info('CheckSenderRegistrationStatusJob: synced', [
            'registration_id' => $reg->id,
            'status'          => $status,
        ]);

        if ($status === WhatsAppSenderRegistration::STATUS_PENDING_APPROVAL) {
            static::dispatch($reg)->delay(now()->addMinutes(15));
        }
    }
}
