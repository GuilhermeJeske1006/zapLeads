<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\InboundMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Opt-out check and bot for an inbound message, so the webhook never waits on the AI or Twilio. */
class ProcessInboundMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // A lost opt-out is worse than a confirmation sent twice.
    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 60;

    public function __construct(
        public readonly int $messageId,
    ) {}

    public function handle(InboundMessageService $inbound): void
    {
        if ($message = Message::find($this->messageId)) {
            $inbound->process($message);
        }
    }
}
