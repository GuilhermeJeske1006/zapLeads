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
        $loja = $this->lead->loja;

        $message = __('messages.follow_up_message', [
            'nome' => $this->lead->nome,
            'loja' => $loja->nome,
        ]);

        $whatsApp->sendTextMessage($this->lead->telefone, $message);
    }
}
