<?php

namespace App\Services;

use App\Jobs\AutoRespondJob;
use App\Jobs\ProcessInboundMessageJob;
use App\Models\Conversation;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\Message;
use App\Models\OutreachAttempt;
use App\Models\OutreachDraft;
use App\Models\SequenceEnrollment;
use App\Models\WhatsAppChannel;
use App\Services\Costs\UsageMeter;
use Illuminate\Support\Facades\Log;

/**
 * What follows an inbound WhatsApp message, off the webhook so Twilio gets its answer at once:
 * the opt-out check (the fast model reads ambiguous messages), its confirmation, and the bot.
 */
class InboundMessageService
{
    public function __construct(
        private readonly OptOutDetector $optOut,
        private readonly WhatsAppService $whatsApp,
        private readonly ChannelHealthService $health,
        private readonly UsageMeter $meter,
    ) {}

    /** The webhook saved $message: the rest runs in the background. */
    public function received(Message $message): void
    {
        ProcessInboundMessageJob::dispatch($message->id);
    }

    /** ProcessInboundMessageJob. A contact who already opted out gets neither a new confirmation nor the bot. */
    public function process(Message $message): void
    {
        $conversation = $message->conversation;
        $empresa = $conversation?->empresa;
        if (!$empresa || $conversation->status === 'blocked') {
            return;
        }

        $optedOut = $this->meter->within(
            ['empresa_id' => $empresa->id, 'lead_id' => $conversation->lead_id, 'origem' => 'conversa'],
            fn () => $this->checkOptOut($empresa, $conversation, (string) $message->message),
        );

        if (!$optedOut && $empresa->bot_ativo && $this->isBotActiveNow($empresa)) {
            AutoRespondJob::dispatch($conversation, (string) $message->message)->delay(now()->addSeconds(3));
        }
    }

    /** Records the opt-out of the number, if that's what $text asks. */
    private function checkOptOut(Empresa $empresa, Conversation $conversation, string $text): bool
    {
        if (!$this->optOut->isOptOut($text)) {
            return false;
        }

        // Before recording it: afterwards nothing can be sent to the number.
        $this->confirmOptOut($empresa, $conversation);

        // Every lead of this empresa with the number, whatever format it was saved in.
        $leadIds = Lead::where('empresa_id', $empresa->id)
            ->where('telefone_e164', $conversation->telefone_e164)
            ->whereNull('opted_out_at')
            ->pluck('id');

        Lead::whereKey($leadIds)->update(['opted_out_at' => now()]);
        $conversation->update(['status' => 'blocked']);

        SequenceEnrollment::whereIn('lead_id', $leadIds)
            ->where('status', 'active')
            ->update(['status' => 'opted_out']);

        OutreachDraft::where('empresa_id', $empresa->id)
            ->whereIn('lead_id', $leadIds)
            ->whereIn('status', OutreachDraft::PENDING)
            ->update(['status' => 'skipped']);

        Log::info('Contact opted out', ['lead_ids' => $leadIds->all(), 'conversation_id' => $conversation->id]);

        // Prospects asking to stop hurt the number's quality: the channels that approached them are checked.
        $channelIds = OutreachAttempt::whereIn('lead_id', $leadIds)->whereNotNull('whatsapp_channel_id')->distinct()->pluck('whatsapp_channel_id');
        WhatsAppChannel::whereKey($channelIds)->get()->each(fn (WhatsAppChannel $channel) => $this->health->checkOptOutRate($channel));

        return true;
    }

    private function confirmOptOut(Empresa $empresa, Conversation $conversation): void
    {
        $text = __('messages.opt_out_confirmation', [], $empresa->locale ?: 'pt_BR');

        $result = $this->whatsApp->sendTextMessage($conversation->telefone_e164, $text, $empresa->id, $conversation->whatsappChannel);

        Message::create([
            'conversation_id'    => $conversation->id,
            'sender'             => 'user',
            'message'            => $text,
            'type'               => 'text',
            'status'             => $result['success'] ? 'sent' : 'failed',
            'twilio_message_sid' => $result['data']['sid'] ?? null,
            'error_code'         => $result['code'] ?? null,
        ]);
    }

    /** The bot's hours are in the empresa's timezone. */
    private function isBotActiveNow(Empresa $empresa): bool
    {
        if (!$empresa->bot_horario_inicio || !$empresa->bot_horario_fim) {
            return true;
        }
        $now = now($empresa->timezone ?: config('app.timezone'))->format('H:i:s');

        return $now >= $empresa->bot_horario_inicio && $now <= $empresa->bot_horario_fim;
    }
}
