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
        $loja = $this->conversation->loja;

        if (!$loja->bot_ativo) {
            return;
        }

        $aiResponse = $ai->gerarMensagem($this->conversation, $this->incomingText);

        if (empty($aiResponse)) {
            return;
        }

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'sender' => 'user',
            'message' => $aiResponse,
            'type' => 'text',
            'status' => 'sending',
            'ai_generated' => true,
        ]);

        $result = $whatsApp->sendTextMessage($this->conversation->telefone, $aiResponse);

        $message->update([
            'status' => $result['success'] ? 'sent' : 'failed',
            'zapi_message_id' => $result['data']['zaapId'] ?? null,
        ]);
    }
}
