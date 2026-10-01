<?php

namespace App\Services\Prospecting;

use App\Models\OutreachDraft;
use App\Models\WhatsAppChannel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * When the next prospect message may leave a channel: business hours in the empresa's timezone
 * (narrowed to the lead's own opening hours when Google gave them), a daily cap per channel and a
 * random gap between sends, so the number isn't flagged as a bot.
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

    /**
     * The slot for the channel's next send, in the app timezone (what the database stores).
     *
     * @param array{periods?: array}|null $openingHours the lead's leads.horario_funcionamento (Google regularOpeningHours)
     */
    public function nextSlot(WhatsAppChannel $channel, ?array $openingHours = null): CarbonImmutable
    {
        $tz = $this->timezone($channel);
        $limit = max(1, (int) $channel->limite_diario_prospeccao);
        $windows = self::windows($openingHours);

        $slot = CarbonImmutable::now($tz);
        $last = $this->scheduled($channel)->max('scheduled_for');
        if ($last) {
            $slot = $slot->max(CarbonImmutable::parse($last, config('app.timezone'))->setTimezone($tz)->addSeconds($this->gap()));
        }

        // Each full day moves the slot to the next opening, so this ends within a few iterations.
        while (true) {
            $slot = $this->intoBusinessHours($slot, $windows);

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

    /** @param array<int, list<array{string, string}>> $windows ISO weekday => sorted [open, close] */
    private function intoBusinessHours(CarbonImmutable $at, array $windows): CarbonImmutable
    {
        while (true) {
            foreach ($windows[$at->dayOfWeekIso] ?? [] as [$open, $close]) {
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

    /**
     * Our business hours, intersected with the lead's opening hours when known. A lead whose hours
     * never meet ours (or open 24h, or unknown) gets our hours as they are.
     *
     * @return array<int, list<array{string, string}>>
     */
    private static function windows(?array $openingHours): array
    {
        $ours = array_map(fn (array $hours) => [$hours], self::HOURS);
        $theirs = self::leadWindows($openingHours['periods'] ?? []);
        if ($theirs === null) {
            return $ours;
        }

        $windows = [];
        foreach ($ours as $day => $dayWindows) {
            foreach ($dayWindows as [$open, $close]) {
                foreach ($theirs[$day] ?? [] as [$leadOpen, $leadClose]) {
                    $from = max($open, $leadOpen);
                    $to = min($close, $leadClose);
                    if ($from < $to) {
                        $windows[$day][] = [$from, $to];
                    }
                }
            }
            if (isset($windows[$day])) {
                sort($windows[$day]);
            }
        }

        return $windows !== [] ? $windows : $ours;
    }

    /**
     * Google periods ({open: {day 0=Sunday, hour, minute}, close: ...}) as ISO weekday => ["HH:MM", "HH:MM"].
     * Null when there's nothing to narrow by: no periods, or open around the clock (no close).
     *
     * @return array<int, list<array{string, string}>>|null
     */
    private static function leadWindows(array $periods): ?array
    {
        if ($periods === []) {
            return null;
        }

        $windows = [];
        foreach ($periods as $period) {
            if (!isset($period['open']['day'], $period['close'])) {
                return null;
            }

            $day = (int) $period['open']['day'] ?: 7;
            $open = sprintf('%02d:%02d', $period['open']['hour'] ?? 0, $period['open']['minute'] ?? 0);
            // A period that closes after midnight is cut at the end of its opening day.
            $close = ($period['close']['day'] ?? null) === $period['open']['day']
                ? sprintf('%02d:%02d', $period['close']['hour'] ?? 0, $period['close']['minute'] ?? 0)
                : '23:59';

            $windows[$day][] = [$open, $close];
        }

        return $windows;
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
