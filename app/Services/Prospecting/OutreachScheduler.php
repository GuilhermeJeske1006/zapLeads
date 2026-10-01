<?php

namespace App\Services\Prospecting;

use App\Models\OutreachDraft;
use App\Models\WhatsAppChannel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * When the next prospect message may leave a channel: business hours in the empresa's timezone,
 * a daily cap per channel and a random gap between sends, so the number isn't flagged as a bot.
 */
class OutreachScheduler
{
    /** ISO weekday => [open, close]. Saturday morning only, never Sunday. */
    private const HOURS = [
        1 => ['09:00', '18:00'],
        2 => ['09:00', '18:00'],
        3 => ['09:00', '18:00'],
        4 => ['09:00', '18:00'],
        5 => ['09:00', '18:00'],
        6 => ['09:00', '12:00'],
    ];

    /** Seconds between two sends on the same channel. */
    private const GAP = [45, 120];

    /** The slot for the channel's next send, in the app timezone (what the database stores). */
    public function nextSlot(WhatsAppChannel $channel): CarbonImmutable
    {
        $tz = $this->timezone($channel);
        $limit = max(1, (int) $channel->limite_diario_prospeccao);

        $slot = CarbonImmutable::now($tz);
        $last = $this->scheduled($channel)->max('scheduled_for');
        if ($last) {
            $slot = $slot->max(CarbonImmutable::parse($last, config('app.timezone'))->setTimezone($tz)->addSeconds($this->gap()));
        }

        // Each full day moves the slot to the next opening, so this ends within a few iterations.
        while (true) {
            $slot = $this->intoBusinessHours($slot);

            if ($this->countOnDay($channel, $slot) < $limit) {
                return $slot->setTimezone(config('app.timezone'));
            }

            $slot = $slot->addDay()->startOfDay();
        }
    }

    /** Sends scheduled today on the channel, for the "18/30" counter. */
    public function usedToday(WhatsAppChannel $channel): int
    {
        return $this->countOnDay($channel, CarbonImmutable::now($this->timezone($channel)));
    }

    private function intoBusinessHours(CarbonImmutable $at): CarbonImmutable
    {
        while (true) {
            [$open, $close] = self::HOURS[$at->dayOfWeekIso] ?? [null, null];

            if ($open !== null) {
                $opensAt = $at->setTimeFromTimeString($open);
                if ($at->lt($opensAt)) {
                    return $opensAt->addSeconds($this->gap());
                }
                if ($at->lt($at->setTimeFromTimeString($close))) {
                    return $at;
                }
            }

            $at = $at->addDay()->startOfDay();
        }
    }

    private function countOnDay(WhatsAppChannel $channel, CarbonImmutable $day): int
    {
        $appTz = config('app.timezone');

        return $this->scheduled($channel)
            ->whereBetween('scheduled_for', [
                $day->startOfDay()->setTimezone($appTz),
                $day->endOfDay()->setTimezone($appTz),
            ])
            ->count();
    }

    private function scheduled(WhatsAppChannel $channel): Builder
    {
        return OutreachDraft::query()
            ->where('whatsapp_channel_id', $channel->id)
            ->whereIn('status', ['approved', 'sent'])
            ->whereNotNull('scheduled_for');
    }

    private function timezone(WhatsAppChannel $channel): string
    {
        return $channel->empresa?->timezone ?: config('app.timezone');
    }

    private function gap(): int
    {
        return random_int(...self::GAP);
    }
}
