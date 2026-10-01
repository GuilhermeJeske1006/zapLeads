<?php

namespace App\Services;

use App\Models\OutreachAttempt;
use App\Models\WhatsAppChannel;
use App\Notifications\ProspectingPaused;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Keeps prospecting from burning a number. Reads each channel's quality rating, messaging limit and
 * status from Twilio (Meta's numbers) and pauses cold outreach on the channel when the quality drops
 * to LOW, the number goes offline, or too many recent API prospects opted out. Only prospecting
 * stops: replies and chat go on. The user resumes it; it pauses again only on a new drop.
 */
class ChannelHealthService
{
    private const SENDERS_URL = 'https://messaging.twilio.com/v2/Channels/Senders';

    /** API prospects contacted in this window, since the last resume, are checked for opt-outs. */
    private const OPT_OUT_WINDOW_DAYS = 7;
    private const OPT_OUT_MIN_LEADS = 20;
    private const OPT_OUT_MAX_RATE = 0.10;

    public const PAUSE_REASONS = ['quality_low', 'sender_offline', 'opt_out_rate'];

    /**
     * Updates every channel that is a sender of the platform's Twilio account. Numbers that aren't
     * senders (the sandbox) are left as they are. Null when Twilio couldn't be read.
     */
    public function syncFromTwilio(): ?int
    {
        $senders = $this->fetchSenders();
        if ($senders === null) {
            return null;
        }

        $channels = WhatsAppChannel::whereIn('numero', array_keys($senders))->get();
        foreach ($channels as $channel) {
            $this->applySender($channel, $senders[$channel->numero]);
        }

        return $channels->count();
    }

    /** One sender of the Senders API on its channel. Pauses on a drop: to LOW quality, or offline. */
    public function applySender(WhatsAppChannel $channel, array $sender): void
    {
        $quality = strtoupper(trim((string) ($sender['properties']['quality_rating'] ?? ''))) ?: null;
        $status = strtoupper(trim((string) ($sender['status'] ?? ''))) ?: null;
        $previousQuality = $channel->qualidade;
        $previousStatus = $channel->sender_status;

        $channel->fill([
            'qualidade'           => $quality,
            'limite_mensagens'    => mb_substr((string) ($sender['properties']['messaging_limit'] ?? ''), 0, 40) ?: null,
            'sender_status'       => $status ? mb_substr($status, 0, 30) : null,
            'saude_verificada_em' => now(),
        ])->save();

        if ($quality === 'LOW' && $previousQuality !== 'LOW') {
            $this->pause($channel, 'quality_low');
        } elseif ($status === 'OFFLINE' && $previousStatus !== 'OFFLINE') {
            $this->pause($channel, 'sender_offline');
        }
    }

    /**
     * Too many of the prospects this number reached through the API asked to stop: Meta's quality
     * rating follows blocks and reports, and lags. Counted since the last resume.
     */
    public function checkOptOutRate(WhatsAppChannel $channel): void
    {
        if ($channel->isProspectingPaused()) {
            return;
        }

        $since = now()->subDays(self::OPT_OUT_WINDOW_DAYS);
        if ($channel->prospeccao_retomada_em?->gt($since)) {
            $since = $channel->prospeccao_retomada_em;
        }

        $attempts = OutreachAttempt::where('whatsapp_channel_id', $channel->id)
            ->where('canal', 'api')
            ->where('created_at', '>=', $since);

        $reached = (clone $attempts)->distinct()->count('lead_id');
        if ($reached < self::OPT_OUT_MIN_LEADS) {
            return;
        }

        $optedOut = (clone $attempts)
            ->whereHas('lead', fn ($q) => $q->whereColumn('leads.opted_out_at', '>=', 'outreach_attempts.created_at'))
            ->distinct()
            ->count('lead_id');

        if ($optedOut / $reached >= self::OPT_OUT_MAX_RATE) {
            $this->pause($channel, 'opt_out_rate');
        }
    }

    public function pause(WhatsAppChannel $channel, string $reason): void
    {
        if ($channel->isProspectingPaused()) {
            return;
        }

        $channel->update(['prospeccao_pausada_em' => now(), 'pausa_motivo' => $reason]);
        Log::warning('Prospecting paused on WhatsApp channel', ['channel_id' => $channel->id, 'reason' => $reason]);

        $empresa = $channel->empresa;
        $empresa?->user?->notify((new ProspectingPaused($channel))->locale($empresa->locale ?: 'pt_BR'));
    }

    public function resume(WhatsAppChannel $channel): void
    {
        $channel->update(['prospeccao_pausada_em' => null, 'pausa_motivo' => null, 'prospeccao_retomada_em' => now()]);
    }

    /** @return array<string, array>|null senders by number ("whatsapp:+55..."), null when Twilio couldn't be read */
    protected function fetchSenders(): ?array
    {
        $sid = (string) config('twilio.sid');
        $token = (string) config('twilio.token');
        if ($sid === '' || $token === '') {
            return null;
        }

        $senders = [];
        $url = self::SENDERS_URL;
        $query = ['Channel' => 'whatsapp', 'PageSize' => 100];

        for ($page = 0; $url !== null && $page < 10; $page++) {
            try {
                $response = Http::withBasicAuth($sid, $token)->acceptJson()->timeout(15)->get($url, $query);
            } catch (\Throwable $e) {
                Log::warning('Twilio Senders read failed', ['error' => $e->getMessage()]);
                return $page === 0 ? null : $senders;
            }

            if ($response->failed()) {
                Log::warning('Twilio Senders read rejected', ['status' => $response->status(), 'error' => $response->json('message')]);
                return $page === 0 ? null : $senders;
            }

            foreach ($response->json($response->json('meta.key') ?: 'senders') ?? [] as $sender) {
                if (is_array($sender) && !empty($sender['sender_id'])) {
                    $senders[$sender['sender_id']] = $sender;
                }
            }

            // The next page carries the account credentials: only Twilio's own host gets them.
            $next = $response->json('meta.next_page_url');
            $url = is_string($next) && str_starts_with($next, self::SENDERS_URL . '?') ? $next : null;
            $query = [];
        }

        return $senders;
    }
}
