<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\User;
use App\Models\WhatsAppChannel;
use App\Services\Prospecting\OutreachScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyLimitTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'America/Sao_Paulo';

    private WhatsAppChannel $channel;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil', 'timezone' => self::TZ]);
        $this->channel = WhatsAppChannel::create([
            'empresa_id' => $empresa->id, 'nome' => 'Vendas', 'numero' => 'whatsapp:+5547900000001',
            'is_default' => true, 'ativo' => true, 'limite_diario_prospeccao' => 30,
        ]);
        $this->lead = Lead::create(['empresa_id' => $empresa->id, 'nome' => 'Studio Bella', 'telefone' => '+55 47 99280-1006']);
    }

    public function test_31st_send_of_the_day_moves_to_the_next_business_day(): void
    {
        $this->travelToLocal('2026-10-06 10:00'); // Tuesday
        $this->scheduleOn('2026-10-06 09:05', 30);

        $slot = $this->nextLocalSlot();

        $this->assertSame('2026-10-07', $slot->toDateString());
        $this->assertBetweenTimes('09:00:45', '09:02:00', $slot);
    }

    public function test_full_friday_moves_to_saturday_morning(): void
    {
        $this->travelToLocal('2026-10-09 15:00'); // Friday
        $this->scheduleOn('2026-10-09 09:05', 30);

        $this->assertSame('2026-10-10 09', $this->nextLocalSlot()->format('Y-m-d H'));
    }

    public function test_saturday_afternoon_and_sunday_wait_for_monday(): void
    {
        $this->travelToLocal('2026-10-10 13:00'); // Saturday afternoon
        $this->assertSame('2026-10-12 09', $this->nextLocalSlot()->format('Y-m-d H'));

        $this->travelToLocal('2026-10-11 10:00'); // Sunday
        $this->assertSame('2026-10-12 09', $this->nextLocalSlot()->format('Y-m-d H'));
    }

    public function test_sends_on_the_same_channel_are_spaced(): void
    {
        $this->travelToLocal('2026-10-06 10:00');
        $this->scheduleOn('2026-10-06 10:30', 1);

        $this->assertBetweenTimes('10:30:45', '10:32:00', $this->nextLocalSlot());
    }

    public function test_free_slot_during_business_hours_is_now(): void
    {
        $this->travelToLocal('2026-10-06 10:00');

        $this->assertSame('2026-10-06 10:00:00', $this->nextLocalSlot()->format('Y-m-d H:i:s'));
    }

    public function test_used_today_counts_the_channel_sends(): void
    {
        $this->travelToLocal('2026-10-06 10:00');
        $this->scheduleOn('2026-10-06 09:05', 18);
        $this->scheduleOn('2026-10-05 09:05', 5);

        $this->assertSame(18, app(OutreachScheduler::class)->usedToday($this->channel));
    }

    private function nextLocalSlot(): CarbonImmutable
    {
        return app(OutreachScheduler::class)->nextSlot($this->channel)->setTimezone(self::TZ);
    }

    private function travelToLocal(string $datetime): void
    {
        $this->travelTo(CarbonImmutable::parse($datetime, self::TZ));
    }

    private function scheduleOn(string $localStart, int $count): void
    {
        $at = CarbonImmutable::parse($localStart, self::TZ)->utc();

        for ($i = 0; $i < $count; $i++) {
            OutreachDraft::create([
                'empresa_id'          => $this->lead->empresa_id,
                'lead_id'             => $this->lead->id,
                'whatsapp_channel_id' => $this->channel->id,
                'texto_final'         => 'Oi!',
                'status'              => 'sent',
                'scheduled_for'       => $at->subSeconds($count - 1 - $i),
            ]);
        }
    }

    private function assertBetweenTimes(string $from, string $to, CarbonImmutable $slot): void
    {
        $time = $slot->format('H:i:s');
        $this->assertTrue($time >= $from && $time <= $to, "{$time} is not between {$from} and {$to}");
    }
}
