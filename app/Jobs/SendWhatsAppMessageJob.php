<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private Message $message,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $conversation = $this->message->conversation;
        $phone = $conversation->telefone;

        $result = match ($this->message->type) {
            'image' => $whatsApp->sendImageMessage($phone, $this->message->media_url, $this->message->message),
            default => $whatsApp->sendTextMessage($phone, $this->message->message),
        };

        $status = $result['success'] ? 'sent' : 'failed';
        $zapiId = $result['data']['zaapId'] ?? $result['data']['messageId'] ?? null;

        $this->message->update([
            'status' => $status,
            'zapi_message_id' => $zapiId,
        ]);

        if (!$result['success']) {
            Log::warning('WhatsApp message failed', ['message_id' => $this->message->id]);
            $this->fail('WhatsApp API returned failure');
        }
    }
}
