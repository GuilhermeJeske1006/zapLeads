<?php

namespace Tests\Feature;

use App\Jobs\SendOutreachDraftJob;
use App\Livewire\WhatsAppChannels;
use App\Models\Conversation;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachAttempt;
use App\Models\OutreachDraft;
use App\Models\User;
use App\Models\WhatsAppChannel;
use App\Services\ChannelHealthService;
use App\Services\Prospecting\OutreachException;
use App\Services\Prospecting\OutreachService;
use App\Services\WhatsAppService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/** Prospecting stops on a number whose quality drops, so the empresa doesn't lose it. */
class ChannelHealthTest extends TestCase
{
    use RefreshDatabase;

    private const NUMBER = 'whatsapp:+5547900000001';

    private Empresa $empresa;
    private WhatsAppChannel $channel;
    private array $sender;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['twilio.sid' => 'AC' . str_repeat('0', 32), 'twilio.token' => 'token']);
        $this->empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);
        $this->channel = WhatsAppChannel::create(['empresa_id' => $this->empresa->id, 'nome' => 'Vendas', 'numero' => self::NUMBER, 'is_default' => true, 'ativo' => true]);
    }

    public function test_twilio_sync_reads_quality_and_pauses_when_it_drops_to_low(): void
    {
        $sandbox = WhatsAppChannel::create(['empresa_id' => $this->empresa->id, 'nome' => 'Sandbox', 'numero' => 'whatsapp:+14155238886', 'ativo' => true]);
        $this->fakeSenders('HIGH', '1K Customers/24hr');
        $health = app(ChannelHealthService::class);

        $this->assertSame(1, $health->syncFromTwilio());
        $this->channel->refresh();
        $this->assertSame(['HIGH', '1K Customers/24hr', 'ONLINE'], [$this->channel->qualidade, $this->channel->limite_mensagens, $this->channel->sender_status]);
        $this->assertFalse($this->channel->isProspectingPaused());
        $this->assertNull($sandbox->fresh()->qualidade); // not a sender of the account
        Http::assertSent(fn (Request $r) => $r->url() === 'https://messaging.twilio.com/v2/Channels/Senders?Channel=whatsapp&PageSize=100'
            && $r->hasHeader('Authorization', 'Basic ' . base64_encode('AC' . str_repeat('0', 32) . ':token')));

        $this->fakeSenders('MEDIUM');
        $health->syncFromTwilio();
        $this->assertFalse($this->channel->fresh()->isProspectingPaused());

        $this->fakeSenders('LOW');
        $health->syncFromTwilio();
        $this->assertSame('quality_low', $this->channel->fresh()->pausa_motivo);

        // The user resumes: still LOW is not a new drop.
        $health->resume($this->channel->fresh());
        $health->syncFromTwilio();
        $this->assertFalse($this->channel->fresh()->isProspectingPaused());
    }

    public function test_number_going_offline_pauses_prospecting(): void
    {
        $this->fakeSenders('HIGH', status: 'OFFLINE');

        app(ChannelHealthService::class)->syncFromTwilio();

        $this->assertSame('sender_offline', $this->channel->fresh()->pausa_motivo);
    }

    public function test_credentials_only_follow_pages_on_twilios_own_host(): void
    {
        Http::fake(['*' => Http::response([
            'senders' => [['sender_id' => self::NUMBER, 'status' => 'ONLINE', 'properties' => ['quality_rating' => 'HIGH']]],
            'meta'    => ['key' => 'senders', 'next_page_url' => 'https://attacker.example/v2/Channels/Senders?Page=1'],
        ])]);

        app(ChannelHealthService::class)->syncFromTwilio();

        Http::assertSentCount(1);
    }

    public function test_twilio_down_or_no_credentials_changes_nothing(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Authenticate'], 401)]);
        $this->assertNull(app(ChannelHealthService::class)->syncFromTwilio());

        config(['twilio.sid' => null]);
        $this->assertNull(app(ChannelHealthService::class)->syncFromTwilio());
        Http::assertSentCount(1);
    }

    public function test_too_many_opt_outs_among_api_prospects_pause_the_number(): void
    {
        $health = app(ChannelHealthService::class);
        $leads = $this->approachedLeads(20);

        $leads[0]->update(['opted_out_at' => now()]);
        $health->checkOptOutRate($this->channel);
        $this->assertFalse($this->channel->fresh()->isProspectingPaused()); // 1 in 20

        $leads[1]->update(['opted_out_at' => now()]);
        $health->checkOptOutRate($this->channel->fresh());
        $this->assertSame('opt_out_rate', $this->channel->fresh()->pausa_motivo); // 2 in 20

        // After a resume only what comes next counts.
        $this->travel(1)->minutes();
        $health->resume($this->channel->fresh());
        $health->checkOptOutRate($this->channel->fresh());
        $this->assertFalse($this->channel->fresh()->isProspectingPaused());
    }

    public function test_opt_out_received_by_webhook_checks_the_channels_that_approached_the_lead(): void
    {
        config(['twilio.webhook_validate' => false]);
        $whatsApp = Mockery::mock(WhatsAppService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $whatsApp->shouldReceive('createMessage')->andReturn(['sid' => 'SMconfirm', 'status' => 'queued']);
        $this->app->instance(WhatsAppService::class, $whatsApp);
        $leads = $this->approachedLeads(20);
        $leads[0]->update(['opted_out_at' => now()]);

        $this->post(route('webhook.twilio'), [
            'From' => 'whatsapp:' . $leads[1]->telefone_e164, 'To' => self::NUMBER, 'Body' => 'SAIR', 'MessageSid' => 'SM' . str_repeat('1', 32),
        ])->assertNoContent();

        $this->assertTrue($leads[1]->fresh()->isOptedOut());
        $this->assertSame('opt_out_rate', $this->channel->fresh()->pausa_motivo);
    }

    public function test_paused_number_sends_no_prospect_messages(): void
    {
        $outreach = app(OutreachService::class);
        $lead = Lead::create(['empresa_id' => $this->empresa->id, 'nome' => 'Studio Bella', 'telefone' => '+55 47 99280-1006']);
        Conversation::create(['empresa_id' => $this->empresa->id, 'telefone' => $lead->telefone, 'whatsapp_channel_id' => $this->channel->id, 'last_inbound_at' => now()]);
        $draft = OutreachDraft::create(['empresa_id' => $this->empresa->id, 'lead_id' => $lead->id, 'texto_final' => 'Oi! Tudo bem por aí?', 'status' => 'draft']);
        $approved = OutreachDraft::create(['empresa_id' => $this->empresa->id, 'lead_id' => $lead->id, 'texto_final' => 'Oi!', 'status' => 'approved', 'scheduled_for' => now()]);

        app(ChannelHealthService::class)->pause($this->channel, 'quality_low');

        try {
            $outreach->approve($draft);
            $this->fail('A paused channel must refuse the approval');
        } catch (OutreachException $e) {
            $this->assertSame('channel_paused', $e->reason);
        }
        $this->assertSame(['mode' => 'blocked', 'reason' => 'messages.outreach_channel_paused'], $outreach->deliveryPlan($draft->fresh()));

        // Already scheduled: fails at its slot, and "Tentar de novo" brings it back once resumed.
        (new SendOutreachDraftJob($approved->id))->handle($outreach);
        $this->assertSame(['failed', 'channel_paused'], [$approved->fresh()->status, $approved->fresh()->erro]);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_user_resumes_only_their_own_channels(): void
    {
        $health = app(ChannelHealthService::class);
        $health->pause($this->channel, 'opt_out_rate');
        $other = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Outra']);
        $foreign = WhatsAppChannel::create(['empresa_id' => $other->id, 'nome' => 'X', 'numero' => 'whatsapp:+5547900000009', 'ativo' => true]);
        $health->pause($foreign, 'quality_low');

        $this->actingAs($this->empresa->user);
        Livewire::test(WhatsAppChannels::class, ['empresaId' => $this->empresa->id])
            ->assertSee(__('messages.channel_resume'))
            ->call('retomarProspeccao', $this->channel->id);

        $this->assertFalse($this->channel->fresh()->isProspectingPaused());
        $this->assertNotNull($this->channel->fresh()->prospeccao_retomada_em);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(WhatsAppChannels::class, ['empresaId' => $this->empresa->id])->call('retomarProspeccao', $foreign->id);
    }

    public function test_hourly_command_syncs_and_checks_every_active_channel(): void
    {
        $this->fakeSenders('LOW');

        $this->artisan('whatsapp:check-channels')
            ->expectsOutputToContain('Channels synced from Twilio: 1.')
            ->expectsOutputToContain('Channels with prospecting paused: 1.')
            ->assertSuccessful();
    }

    /** @return list<Lead> leads this channel reached through the API */
    private function approachedLeads(int $count): array
    {
        return array_map(function (int $i) {
            $lead = Lead::create(['empresa_id' => $this->empresa->id, 'nome' => "Lead {$i}", 'telefone' => sprintf('+55 47 99100-%04d', $i)]);
            OutreachAttempt::create([
                'empresa_id' => $this->empresa->id, 'lead_id' => $lead->id, 'canal' => 'api', 'whatsapp_channel_id' => $this->channel->id,
                'mensagem' => 'Oi?', 'variante' => 'observacao', 'etapa' => 0,
            ]);

            return $lead;
        }, range(1, $count));
    }

    /** What Twilio says about our number from now on (the fake is registered once and reads this). */
    private function fakeSenders(string $quality, ?string $limit = null, string $status = 'ONLINE'): void
    {
        $registered = isset($this->sender);
        $this->sender = ['sid' => 'XE1', 'sender_id' => self::NUMBER, 'status' => $status, 'properties' => array_filter(['quality_rating' => $quality, 'messaging_limit' => $limit])];

        if (!$registered) {
            Http::fake(['messaging.twilio.com/*' => fn () => Http::response([
                'senders' => [$this->sender, ['sid' => 'XE2', 'sender_id' => 'whatsapp:+5511999990000', 'status' => 'ONLINE', 'properties' => ['quality_rating' => 'HIGH']]],
                'meta'    => ['key' => 'senders', 'next_page_url' => null],
            ])]);
        }
    }
}
