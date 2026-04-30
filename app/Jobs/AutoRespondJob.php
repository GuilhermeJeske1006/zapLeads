<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AIService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoRespondJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        private Conversation $conversation,
        private string $incomingText,
    ) {}

    public function handle(AIService $ai, WhatsAppService $whatsApp): void
    {
        $empresa = $this->conversation->empresa;

        if (!$empresa?->bot_ativo) {
            return;
        }

        $aiResponse = $ai->gerarMensagem($this->conversation, $this->incomingText);

        if (empty($aiResponse)) {
            return;
        }

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'sender'          => 'user',
            'message'         => $aiResponse,
            'type'            => 'text',
            'status'          => 'sending',
            'ai_generated'    => true,
        ]);

        $channel = $this->conversation->whatsappChannel;

        $result = $whatsApp->sendTextMessage(
            $this->conversation->telefone,
            $aiResponse,
            $this->conversation->empresa_id,
            $channel
        );

        if (($result['success'] ?? false) && $this->conversation->lead && (($this->conversation->lead->status ?? 'novo') === 'novo')) {
            $this->conversation->lead->update(['status' => 'contatado']);
        }

        $message->update([
            'status'             => $result['success'] ? 'sent' : 'failed',
            'twilio_message_sid' => $result['data']['sid'] ?? null,
        ]);
    }
}
