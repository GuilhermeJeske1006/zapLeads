<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Models\WhatsAppChannel;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Automatic messages to leads outside prospecting: the follow-up a day after a catalog visit and
 * the steps of "lead_capture" sequences. WhatsApp takes free text only within 24h of the contact's
 * last message; outside it the first approved template the text fills goes instead. With neither,
 * nothing is sent (Twilio would refuse it with 63016). What is sent shows in the chat.
 */
class LeadMessenger
{
    /** Outcomes of send(). */
    public const SENT = 'sent';
    public const TEMPLATE = 'template';
    public const NO_TEMPLATE = 'no_template';
    public const OPTED_OUT = 'opted_out';
    public const INVALID_PHONE = 'invalid_phone';
    public const NO_CHANNEL = 'no_channel';

    /** @return string one of the outcome constants; only SENT and TEMPLATE queued a message */
    public function send(Lead $lead, string $text, ?string $imageUrl = null): string
    {
        if ($lead->isOptedOut()) {
            return self::OPTED_OUT;
        }

        if (!$lead->telefone_e164) {
            return self::INVALID_PHONE;
        }

        $conversation = Conversation::where('empresa_id', $lead->empresa_id)->where('telefone_e164', $lead->telefone_e164)->first();
        if ($conversation?->status === 'blocked') {
            return self::OPTED_OUT;
        }

        $channel = $this->channelFor($lead, $conversation);
        if (!$channel) {
            return self::NO_CHANNEL;
        }

        $template = null;
        if (!$conversation?->isSessionOpen()) {
            $template = WhatsAppTemplate::firstFilled($lead->empresa->whatsappTemplates()->usable()->orderBy('id')->get(), $lead, $text);

            if ($template === null) {
                Log::info('Lead message not sent: 24h session closed and no approved template fits', ['lead_id' => $lead->id]);
                return self::NO_TEMPLATE;
            }
        }

        // A template carries only its own text: the image goes only inside the session.
        $body = $template ? $template['template']->render($template['variables']) : $text;
        $image = $template ? null : $imageUrl;

        $conversation ??= Conversation::create([
            'empresa_id'          => $lead->empresa_id,
            'telefone'            => $lead->telefone,
            'lead_id'             => $lead->id,
            'nome_contato'        => $lead->nome,
            'status'              => 'active',
            'whatsapp_channel_id' => $channel->id,
        ]);
        $conversation->fill([
            'whatsapp_channel_id' => $channel->id,
            'lead_id'             => $conversation->lead_id ?? $lead->id,
            'last_message'        => $body,
            'last_message_at'     => now(),
        ])->save();

        $message = Message::create([
            'conversation_id'   => $conversation->id,
            'sender'            => 'user',
            'message'           => $body,
            'type'              => $image ? 'image' : 'text',
            'media_url'         => $image,
            'status'            => 'sending',
            'content_sid'       => $template['template']->content_sid ?? null,
            'content_variables' => $template['variables'] ?? null,
        ]);

        SendWhatsAppMessageJob::dispatch($message);

        return $template ? self::TEMPLATE : self::SENT;
    }

    /** The number the contact already talks to, or the empresa's default; active channels of the empresa only. */
    private function channelFor(Lead $lead, ?Conversation $conversation): ?WhatsAppChannel
    {
        $current = $conversation?->whatsappChannel;
        if ($current && $current->ativo && (int) $current->empresa_id === (int) $lead->empresa_id) {
            return $current;
        }

        return $lead->empresa->defaultChannel();
    }
}
