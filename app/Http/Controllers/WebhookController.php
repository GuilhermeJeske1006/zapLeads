<?php

namespace App\Http\Controllers;

use App\Events\MessageStatusUpdated;
use App\Events\NewMessageReceived;
use App\Jobs\AutoRespondJob;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Empresa;
use App\Models\Message;
use App\Models\SequenceEnrollment;
use App\Models\WhatsAppChannel;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function twilio(Request $request): Response
    {
        $payload = $request->all();
        Log::info('Twilio Webhook received', $payload);

        $status     = $payload['MessageStatus'] ?? $payload['SmsStatus'] ?? null;
        $messageSid = $payload['MessageSid'] ?? null;

        if ($messageSid && $status && !isset($payload['Body'])) {
            $this->handleStatusCallback($messageSid, $status);
            return response('', 204);
        }

        if (isset($payload['Body'])) {
            $this->handleIncomingMessage($payload);
        }

        return response('', 204);
    }

    private function handleIncomingMessage(array $payload): void
    {
        $rawFrom    = $payload['From'] ?? null;  // "whatsapp:+5511999999999"
        $toNumber   = $payload['To'] ?? null;    // "whatsapp:+5511000000000" (empresa's number)
        $text       = $payload['Body'] ?? null;
        $messageSid = $payload['MessageSid'] ?? null;
        $profileName = $payload['ProfileName'] ?? null;

        if (!$rawFrom || $text === null) {
            return;
        }

        // Twilio sends E.164; canonical() also restores the 9th digit Brazilian mobiles arrive without.
        $phone = Phone::canonical($rawFrom);
        if ($phone === null) {
            return;
        }

        $channel = $this->resolveInboundChannel($toNumber, $phone);

        if (!$channel) {
            Log::warning('Twilio inbound to a number without channel, dropping', ['to' => $toNumber]);
            return;
        }

        $empresa = $channel->empresa;

        $conversation = Conversation::firstOrCreate(
            ['empresa_id' => $empresa->id, 'telefone_e164' => $phone],
            [
                'telefone'            => $phone,
                'nome_contato'        => $profileName,
                'whatsapp_channel_id' => $channel->id,
                'status'              => 'active',
            ]
        );

        if ($conversation->whatsapp_channel_id === null) {
            $conversation->update(['whatsapp_channel_id' => $channel->id]);
        }

        $message = Message::create([
            'conversation_id'    => $conversation->id,
            'sender'             => 'lead',
            'message'            => $text,
            'type'               => 'text',
            'status'             => 'delivered',
            'twilio_message_sid' => $messageSid,
        ]);

        $conversation->update([
            'last_message'    => $text,
            'last_message_at' => now(),
            'unread_count'    => $conversation->unread_count + 1,
        ]);

        broadcast(new NewMessageReceived($message, $conversation));

        $this->checkOptOut($empresa, $phone, $text, $conversation);

        if ($empresa->bot_ativo && $conversation->status !== 'blocked' && $this->isBotActiveNow($empresa)) {
            AutoRespondJob::dispatch($conversation, $text)->delay(now()->addSeconds(3));
        }
    }

    /**
     * Inbound messages belong to the empresa that owns the receiving number. Never guess a
     * tenant: a number without channel is dropped instead of landing in another inbox.
     */
    private function resolveInboundChannel(?string $toNumber, string $phone): ?WhatsAppChannel
    {
        if (!$toNumber) {
            return null;
        }

        $channels = WhatsAppChannel::where('numero', $toNumber)->with('empresa')->get();

        if ($channels->pluck('empresa_id')->unique()->count() <= 1) {
            return $channels->first();
        }

        // Several empresas registered the same number (e.g. the Twilio sandbox in dev):
        // route to the one already talking to this contact, or drop.
        $empresaId = Conversation::whereIn('empresa_id', $channels->pluck('empresa_id'))
            ->where('telefone_e164', $phone)
            ->orderByDesc('last_message_at')
            ->value('empresa_id');

        return $empresaId ? $channels->firstWhere('empresa_id', $empresaId) : null;
    }

    private function handleStatusCallback(string $messageSid, string $status): void
    {
        $normalized = strtolower($status);
        if (!in_array($normalized, ['sent', 'delivered', 'read', 'failed', 'undelivered'])) {
            return;
        }

        $finalStatus = $normalized === 'undelivered' ? 'failed' : $normalized;

        $message = Message::where('twilio_message_sid', $messageSid)->first();
        if (!$message) return;

        $message->update(['status' => $finalStatus]);
        broadcast(new MessageStatusUpdated($message));
    }

    private function isBotActiveNow(Empresa $empresa): bool
    {
        if (!$empresa->bot_horario_inicio || !$empresa->bot_horario_fim) {
            return true;
        }
        $now = now()->format('H:i:s');
        return $now >= $empresa->bot_horario_inicio && $now <= $empresa->bot_horario_fim;
    }

    private function checkOptOut(Empresa $empresa, string $phone, string $text, Conversation $conversation): void
    {
        $optOutKeywords = ['sair', 'stop', 'parar', 'cancelar', 'não quero', 'nao quero', 'remover'];

        if (!in_array(mb_strtolower(trim($text)), $optOutKeywords)) {
            return;
        }

        // Every lead of this empresa with the number, whatever format it was saved in.
        $leadIds = Lead::where('empresa_id', $empresa->id)
            ->where('telefone_e164', $phone)
            ->whereNull('opted_out_at')
            ->pluck('id');

        if ($leadIds->isEmpty()) {
            return;
        }

        Lead::whereKey($leadIds)->update(['opted_out_at' => now()]);
        $conversation->update(['status' => 'blocked']);

        SequenceEnrollment::whereIn('lead_id', $leadIds)
            ->where('status', 'active')
            ->update(['status' => 'opted_out']);

        Log::info('Lead opted out', ['lead_ids' => $leadIds->all(), 'phone' => $phone]);
    }
}
