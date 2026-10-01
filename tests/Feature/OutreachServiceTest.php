<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\User;
use App\Models\WhatsAppChannel;
use App\Services\AIService;
use App\Services\Prospecting\OutreachException;
use App\Services\Prospecting\OutreachService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class OutreachServiceTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->empresa = $this->empresa();
    }

    public function test_unreachable_leads_are_refused_before_writing_anything(): void
    {
        $this->ai(never: true);
        $this->channel($this->empresa);

        $this->assertRefused('no_phone', $this->lead(''));
        $this->assertRefused('invalid_phone', $this->lead('9280-1006'));
        $this->assertRefused('opted_out', $this->lead('+55 47 99280-1006', optedOut: true));
        $this->assertDatabaseCount('outreach_drafts', 0);
    }

    public function test_requires_a_channel_of_the_lead_empresa(): void
    {
        $this->ai(never: true);
        $lead = $this->lead('+55 47 99280-1006');

        $this->assertRefused('no_channel', $lead);
        $this->assertRefused('no_channel', $lead, $this->channel($this->empresa()));
    }

    public function test_prepare_writes_the_message_for_the_default_channel(): void
    {
        $this->ai('Oi! Vocês ainda marcam horário só pelo WhatsApp?');
        $channel = $this->channel($this->empresa);

        $draft = app(OutreachService::class)->prepare($this->lead('+55 47 99280-1006'));

        $this->assertSame('draft', $draft->status);
        $this->assertSame($channel->id, $draft->whatsapp_channel_id);
        $this->assertSame('Oi! Vocês ainda marcam horário só pelo WhatsApp?', $draft->texto_final);
    }

    public function test_failed_generation_leaves_no_draft(): void
    {
        $this->ai('');
        $this->channel($this->empresa);

        $this->assertRefused('generation_failed', $this->lead('+55 47 99280-1006'));
        $this->assertDatabaseCount('outreach_drafts', 0);
    }

    public function test_send_reuses_the_contact_conversation_and_its_channel(): void
    {
        $this->ai('Oi!');
        $original = $this->channel($this->empresa, 'whatsapp:+5547900000001');
        $default = $this->channel($this->empresa, 'whatsapp:+5547900000002');
        $conversation = Conversation::create([
            'empresa_id' => $this->empresa->id, 'telefone' => '554792801006', 'status' => 'active', 'whatsapp_channel_id' => $original->id,
        ]);
        $outreach = app(OutreachService::class);

        $message = $outreach->send($outreach->prepare($this->lead('(47) 99280-1006'), $default));

        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertSame($original->id, $conversation->fresh()->whatsapp_channel_id);
        $this->assertSame('Oi!', $conversation->fresh()->last_message);
        $this->assertSame('sent', OutreachDraft::sole()->status);
        Queue::assertPushed(SendWhatsAppMessageJob::class);
    }

    private function assertRefused(string $reason, Lead $lead, ?WhatsAppChannel $channel = null): void
    {
        try {
            app(OutreachService::class)->prepare($lead, $channel);
            $this->fail("Expected outreach to be refused with {$reason}");
        } catch (OutreachException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    private function ai(string $text = '', bool $never = false): void
    {
        $ai = Mockery::mock(AIService::class);
        $never
            ? $ai->shouldNotReceive('gerarPrimeiraMensagemProspeccao')
            : $ai->shouldReceive('gerarPrimeiraMensagemProspeccao')->andReturn($text);
        $this->app->instance(AIService::class, $ai);
    }

    private function lead(string $telefone, bool $optedOut = false): Lead
    {
        return Lead::create([
            'empresa_id'   => $this->empresa->id,
            'nome'         => 'Studio Bella',
            'telefone'     => $telefone,
            'opted_out_at' => $optedOut ? now() : null,
        ]);
    }

    private function channel(Empresa $empresa, string $numero = 'whatsapp:+5547900000001'): WhatsAppChannel
    {
        return WhatsAppChannel::create(['empresa_id' => $empresa->id, 'nome' => 'Vendas', 'numero' => $numero, 'is_default' => true, 'ativo' => true]);
    }

    private function empresa(): Empresa
    {
        return Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);
    }
}
