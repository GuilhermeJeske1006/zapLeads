<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        private Campaign $campaign,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $this->campaign->update(['status' => 'running', 'started_at' => now()]);

        $leads = $this->campaign->leads()
            ->wherePivot('status', 'pending')
            ->whereNull('opted_out_at')
            ->get();

        foreach ($leads as $lead) {
            try {
                $result = $this->campaign->imagem
                    ? $whatsApp->sendImageMessage($lead->telefone, asset('storage/' . $this->campaign->imagem), $this->campaign->mensagem)
                    : $whatsApp->sendTextMessage($lead->telefone, $this->campaign->mensagem);

                $status = $result['success'] ? 'sent' : 'failed';

                DB::table('campaign_leads')
                    ->where('campaign_id', $this->campaign->id)
                    ->where('lead_id', $lead->id)
                    ->update(['status' => $status, 'sent_at' => now()]);

                if ($result['success']) {
                    $this->campaign->increment('total_enviados');
                } else {
                    $this->campaign->increment('total_erros');
                }

                sleep(2); // Z-API rate limit
            } catch (\Throwable) {
                $this->campaign->increment('total_erros');
            }
        }

        $this->campaign->update(['status' => 'completed', 'completed_at' => now()]);
    }
}
