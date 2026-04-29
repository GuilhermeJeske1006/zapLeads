<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FollowUpWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private Lead $lead,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $empresa = $this->lead->empresa;
        $channel = $empresa?->defaultChannel();

        $message = __('messages.follow_up_message', [
            'nome' => $this->lead->nome,
            'loja' => $empresa?->nome,
        ]);

        $whatsApp->sendTextMessage($this->lead->telefone, $message, $this->lead->empresa_id, $channel);
    }
}
