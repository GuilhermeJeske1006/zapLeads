<?php

namespace App\Services\Prospecting;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Models\OutreachDraft;
use App\Models\WhatsAppChannel;
use App\Services\AIService;

/**
 * The one place that decides whether and how a prospect is approached: phone, opt-out and channel
 * checks, the message, and queuing it on the lead's conversation.
 */
class OutreachService
{
    public function __construct(
        private readonly AIService $ai,
    ) {}

    /** @throws OutreachException when the lead can't receive WhatsApp messages */
    public function assertReachable(Lead $lead): void
    {
        if (trim((string) $lead->telefone) === '') {
            throw new OutreachException('no_phone');
        }

        if (!$lead->telefone_e164) {
            throw new OutreachException('invalid_phone');
        }

        if ($lead->isOptedOut()) {
            throw new OutreachException('opted_out');
        }
    }

    /**
     * Writes the first message for the lead, to go out through $channel or the empresa's default.
     *
     * @throws OutreachException
     */
    public function prepare(Lead $lead, ?WhatsAppChannel $channel = null): OutreachDraft
    {
        $this->assertReachable($lead);

        $empresa = $lead->empresa;
        $channel ??= $empresa->defaultChannel();

        if (!$channel || (int) $channel->empresa_id !== (int) $empresa->id) {
            throw new OutreachException('no_channel');
        }

        $text = $this->ai->gerarPrimeiraMensagemProspeccao($empresa, $lead);
        if (trim($text) === '') {
            throw new OutreachException('generation_failed');
        }

        return OutreachDraft::create([
            'empresa_id'          => $empresa->id,
            'lead_id'             => $lead->id,
            'whatsapp_channel_id' => $channel->id,
            'texto_final'         => $text,
            'status'              => 'draft',
        ]);
    }

    /**
     * Queues the draft on the lead's conversation. An existing conversation keeps its channel so
     * the contact always hears from the same number.
     *
     * @throws OutreachException
     */
    public function send(OutreachDraft $draft): Message
    {
        $lead = $draft->lead;
        $this->assertReachable($lead);

        $conversation = Conversation::firstOrCreate(
            ['empresa_id' => $draft->empresa_id, 'telefone_e164' => $lead->telefone_e164],
            [
                'telefone'            => $lead->telefone,
                'lead_id'             => $lead->id,
                'nome_contato'        => $lead->nome,
                'status'              => 'active',
                'whatsapp_channel_id' => $draft->whatsapp_channel_id,
            ]
        );

        $conversation->fill([
            'whatsapp_channel_id' => $conversation->whatsapp_channel_id ?? $draft->whatsapp_channel_id,
            'lead_id'             => $conversation->lead_id ?? $lead->id,
            'last_message'        => $draft->texto_final,
            'last_message_at'     => now(),
            'status'              => 'active',
        ])->save();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender'          => 'user',
            'message'         => $draft->texto_final,
            'type'            => 'text',
            'status'          => 'sending',
            'ai_generated'    => true,
        ]);

        SendWhatsAppMessageJob::dispatch($message);

        $draft->update(['status' => 'sent', 'sent_at' => now()]);

        return $message;
    }
}
