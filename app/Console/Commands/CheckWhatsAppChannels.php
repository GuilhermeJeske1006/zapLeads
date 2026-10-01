<?php

namespace App\Console\Commands;

use App\Models\WhatsAppChannel;
use App\Services\ChannelHealthService;
use Illuminate\Console\Command;

class CheckWhatsAppChannels extends Command
{
    protected $signature = 'whatsapp:check-channels';

    protected $description = "Read each channel's quality and messaging limit from Twilio and pause prospecting on numbers at risk (scheduled hourly)";

    public function handle(ChannelHealthService $health): int
    {
        $synced = $health->syncFromTwilio();
        $this->info($synced === null ? 'Twilio Senders not read (no credentials or request failed).' : "Channels synced from Twilio: {$synced}.");

        WhatsAppChannel::where('ativo', true)->whereNull('prospeccao_pausada_em')->each(fn (WhatsAppChannel $channel) => $health->checkOptOutRate($channel));

        $paused = WhatsAppChannel::whereNotNull('prospeccao_pausada_em')->count();
        $this->info("Channels with prospecting paused: {$paused}.");

        return self::SUCCESS;
    }
}
