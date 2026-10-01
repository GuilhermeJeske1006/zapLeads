<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Lead;
use App\Services\LeadMessenger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * A day after a catalog visit, asks the lead whether they need help. Skipped when the conversation
 * already moved (the lead or the empresa wrote in the meantime, or the lead left "novo").
 */
class FollowUpWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        private Lead $lead,
    ) {}

    public function handle(LeadMessenger $messenger): void
    {
        $lead = $this->lead;
        $empresa = $lead->empresa;

        $talking = Conversation::where('empresa_id', $lead->empresa_id)
            ->where('telefone_e164', $lead->telefone_e164)
            ->where('last_message_at', '>=', now()->subDay())
            ->exists();

        if (!$empresa || ($lead->status ?? 'novo') !== 'novo' || $talking) {
            return;
        }

        $text = __('messages.follow_up_message', ['nome' => $lead->nome, 'loja' => $empresa->nome], $empresa->locale ?: 'pt_BR');
        $outcome = $messenger->send($lead, $text);

        if (!in_array($outcome, [LeadMessenger::SENT, LeadMessenger::TEMPLATE], true)) {
            Log::info('Catalog follow-up not sent', ['lead_id' => $lead->id, 'outcome' => $outcome]);
        }
    }
}
