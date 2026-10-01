<?php

namespace App\Jobs;

use App\Models\Lead;
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
        $phone        = $conversation->telefone;
        $empresaId    = $conversation->empresa_id;
        $channel      = $conversation->whatsappChannel;

        $result = match ($this->message->type) {
            'image' => $whatsApp->sendImageMessage($phone, $this->message->media_url, $this->message->message, $empresaId, $channel),
            default => $whatsApp->sendTextMessage($phone, $this->message->message, $empresaId, $channel),
        };

        $status = $result['success'] ? 'sent' : 'failed';

        $this->message->update([
            'status'             => $status,
            'twilio_message_sid' => $result['data']['sid'] ?? null,
        ]);

        if ($result['success']) {
            $lead = $conversation->lead_id
                ? $conversation->lead
                : ($conversation->telefone_e164
                    ? Lead::where('empresa_id', $empresaId)->where('telefone_e164', $conversation->telefone_e164)->first()
                    : null);

            if ($lead && ($lead->status ?? 'novo') === 'novo') {
                $lead->update(['status' => 'contatado']);
            }
        }

        if (!$result['success']) {
            Log::warning('WhatsApp message failed', ['message_id' => $this->message->id]);
            $this->fail('WhatsApp API returned failure');
        }
    }
}
