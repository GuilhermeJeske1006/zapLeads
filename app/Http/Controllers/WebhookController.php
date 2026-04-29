<?php

namespace App\Http\Controllers;

use App\Events\MessageStatusUpdated;
use App\Events\NewMessageReceived;
use App\Jobs\AutoRespondJob;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Loja;
use App\Models\Message;
use App\Models\SequenceEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function zapi(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('Z-API Webhook received', $payload);

        // Z-API sends different event types
        $type = $payload['type'] ?? null;

        if ($type === 'ReceivedCallback') {
            $this->handleIncomingMessage($payload);
        } elseif ($type === 'MessageStatusCallback') {
            $this->handleStatusCallback($payload);
        }

        return response()->json(['ok' => true]);
    }

    private function handleIncomingMessage(array $payload): void
    {
        $phone = $payload['phone'] ?? null;
        $text = $payload['text']['message'] ?? null;
        $zapiId = $payload['messageId'] ?? null;
        $nome = $payload['senderName'] ?? null;

        if (!$phone || !$text) {
            return;
        }

        // Find loja by instance — in multi-tenant, use X-Loja-ID header or dedicated instance per loja
        $lojaId = request()->header('X-Loja-ID');
        $loja = $lojaId ? Loja::find($lojaId) : Loja::first();

        if (!$loja) {
            return;
        }

        $conversation = Conversation::firstOrCreate(
            ['loja_id' => $loja->id, 'telefone' => $phone],
            ['nome_contato' => $nome, 'status' => 'active']
        );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'lead',
            'message' => $text,
            'type' => 'text',
            'status' => 'delivered',
            'zapi_message_id' => $zapiId,
        ]);

        $conversation->update([
            'last_message' => $text,
            'last_message_at' => now(),
            'unread_count' => $conversation->unread_count + 1,
        ]);

        broadcast(new NewMessageReceived($message, $conversation));

        $this->checkOptOut($loja, $phone, $text, $conversation);

        if ($loja->bot_ativo && $conversation->status !== 'blocked' && $this->isBotActiveNow($loja)) {
            AutoRespondJob::dispatch($conversation, $text)->delay(now()->addSeconds(3));
        }
    }

    private function handleStatusCallback(array $payload): void
    {
        $zapiMessageId = $payload['messageId'] ?? null;
        $newStatus = strtolower($payload['status'] ?? '');

        if (!$zapiMessageId || !in_array($newStatus, ['sent', 'delivered', 'read', 'failed'])) {
            return;
        }

        $message = Message::where('zapi_message_id', $zapiMessageId)->first();
        if (!$message) return;

        $message->update(['status' => $newStatus]);
        broadcast(new MessageStatusUpdated($message));
    }

    private function isBotActiveNow(Loja $loja): bool
    {
        if (!$loja->bot_horario_inicio || !$loja->bot_horario_fim) {
            return true;
        }
        $now = now()->format('H:i:s');
        return $now >= $loja->bot_horario_inicio && $now <= $loja->bot_horario_fim;
    }

    private function checkOptOut(Loja $loja, string $phone, string $text, Conversation $conversation): void
    {
        $optOutKeywords = ['sair', 'stop', 'parar', 'cancelar', 'não quero', 'nao quero', 'remover'];

        if (!in_array(mb_strtolower(trim($text)), $optOutKeywords)) {
            return;
        }

        $lead = Lead::where('loja_id', $loja->id)->where('telefone', $phone)->first();

        if ($lead && !$lead->isOptedOut()) {
            $lead->update(['opted_out_at' => now()]);
            $conversation->update(['status' => 'blocked']);

            SequenceEnrollment::where('lead_id', $lead->id)
                ->where('status', 'active')
                ->update(['status' => 'opted_out']);

            Log::info('Lead opted out', ['lead_id' => $lead->id, 'phone' => $phone]);
        }
    }
}
