<?php

namespace Tests\Feature;

use App\Jobs\GenerateOutreachDraftJob;
use App\Jobs\SendOutreachDraftJob;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\Message;
use App\Models\OutreachAttempt;
use App\Models\OutreachDraft;
use App\Models\User;
use App\Models\WhatsAppChannel;
use App\Models\WhatsAppTemplate;
use App\Services\AIService;
use App\Services\Prospecting\OutreachException;
use App\Services\Prospecting\OutreachService;
use App\Services\WhatsAppService;
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

    public function test_unreachable_leads_are_refused_before_queuing_anything(): void
    {
        $this->ai(never: true);

        $this->assertRefused('no_phone', fn () => $this->outreach()->request($this->lead('')));
        $this->assertRefused('invalid_phone', fn () => $this->outreach()->request($this->lead('9280-1006')));
        $this->assertRefused('opted_out', fn () => $this->outreach()->request($this->lead('+55 47 99280-1006', optedOut: true)));
        $this->assertDatabaseCount('outreach_drafts', 0);
        Queue::assertNothingPushed();
    }

    public function test_contact_who_opted_out_before_becoming_a_lead_is_refused(): void
    {
        Conversation::create(['empresa_id' => $this->empresa->id, 'telefone' => '554792801006', 'status' => 'blocked']);

        $this->assertRefused('opted_out', fn () => $this->outreach()->request($this->lead('(47) 99280-1006')));
    }

    public function test_request_queues_the_writing_without_needing_a_channel(): void
    {
        $draft = $this->outreach()->request($this->lead('+55 47 99280-1006'));

        $this->assertSame('generating', $draft->status);
        $this->assertNull($draft->whatsapp_channel_id);
        Queue::assertPushed(GenerateOutreachDraftJob::class, fn ($job) => $job->draftId === $draft->id);
    }

    public function test_request_refuses_a_channel_of_another_empresa(): void
    {
        $other = $this->channel($this->empresa());

        $this->assertRefused('no_channel', fn () => $this->outreach()->request($this->lead('+55 47 99280-1006'), $other));
    }

    public function test_a_lead_in_the_queue_keeps_its_draft(): void
    {
        $lead = $this->lead('+55 47 99280-1006');

        $first = $this->outreach()->request($lead);
        $second = $this->outreach()->request($lead);

        $this->assertSame($first->id, $second->id);
        Queue::assertPushed(GenerateOutreachDraftJob::class, 1);
    }

    public function test_prepare_writes_the_message_for_review(): void
    {
        $this->ai('Oi! Vocês ainda marcam horário só pelo WhatsApp?');
        $draft = $this->outreach()->request($this->lead('+55 47 99280-1006'));

        (new GenerateOutreachDraftJob($draft->id))->handle($this->outreach());

        $draft->refresh();
        $this->assertSame('draft', $draft->status);
        $this->assertSame('Oi! Vocês ainda marcam horário só pelo WhatsApp?', $draft->texto_final);
        Queue::assertNotPushed(SendOutreachDraftJob::class);
    }

    public function test_failed_generation_marks_the_draft_failed(): void
    {
        $this->ai('');
        $draft = $this->outreach()->request($this->lead('+55 47 99280-1006'));

        (new GenerateOutreachDraftJob($draft->id))->handle($this->outreach());

        $this->assertSame('failed', $draft->fresh()->status);
        $this->assertSame('generation_failed', $draft->fresh()->erro);
    }

    public function test_closed_session_without_template_is_refused(): void
    {
        $this->channel($this->empresa);
        $draft = $this->draft($this->lead('+55 47 99280-1006'));

        $this->assertRefused('session_closed', fn () => $this->outreach()->approve($draft));
        $this->assertSame('draft', $draft->fresh()->status);
        Queue::assertNotPushed(SendOutreachDraftJob::class);
    }

    public function test_closed_session_goes_out_as_the_approved_template(): void
    {
        $channel = $this->channel($this->empresa);
        $this->template(['1' => 'lead_nome', '2' => 'mensagem']);
        $draft = $this->draft($this->lead('+55 47 99280-1006'), "Vocês ainda marcam\nhorário pelo WhatsApp?");

        $this->outreach()->approve($draft);
        $this->assertSame('approved', $draft->fresh()->status);
        Queue::assertPushed(SendOutreachDraftJob::class);

        $message = $this->outreach()->send($draft->fresh());

        $this->assertSame('HX' . str_repeat('a', 32), $message->content_sid);
        $this->assertSame("Olá Studio Bella! Vocês ainda marcam horário pelo WhatsApp?", $message->message);

        $this->deliver($message, fn (array $params) => $params['contentSid'] === 'HX' . str_repeat('a', 32)
            && json_decode($params['contentVariables'], true) === ['1' => 'Studio Bella', '2' => 'Vocês ainda marcam horário pelo WhatsApp?']
            && !isset($params['body'])
            && $params['from'] === $channel->numero);
    }

    public function test_open_session_goes_out_as_free_text(): void
    {
        $this->channel($this->empresa);
        $this->template(['1' => 'lead_nome']);
        $lead = $this->lead('+55 47 99280-1006');
        Conversation::create([
            'empresa_id' => $this->empresa->id, 'telefone' => '+5547992801006', 'status' => 'active', 'last_inbound_at' => now()->subHours(3),
        ]);
        $draft = $this->draft($lead);

        $this->outreach()->approve($draft, 'Oi! Posso te mandar um vídeo de 1 minuto?');
        $message = $this->outreach()->send($draft->fresh());

        $this->assertNull($message->content_sid);
        $this->deliver($message, fn (array $params) => $params['body'] === 'Oi! Posso te mandar um vídeo de 1 minuto?'
            && !isset($params['contentSid']));
    }

    public function test_template_with_an_unmapped_placeholder_is_refused(): void
    {
        $this->channel($this->empresa);
        $this->template(['1' => 'lead_nome', '2' => null]);

        $this->assertRefused('template_incomplete', fn () => $this->outreach()->approve($this->draft($this->lead('+55 47 99280-1006'))));
    }

    public function test_approval_needs_an_active_channel(): void
    {
        $this->template(['1' => 'lead_nome']);
        $this->channel($this->empresa)->update(['ativo' => false]);

        $this->assertRefused('no_channel', fn () => $this->outreach()->approve($this->draft($this->lead('+55 47 99280-1006'))));
    }

    public function test_send_reuses_the_contact_conversation_and_its_channel(): void
    {
        $original = $this->channel($this->empresa, 'whatsapp:+5547900000001');
        $default = $this->channel($this->empresa, 'whatsapp:+5547900000002');
        $conversation = Conversation::create([
            'empresa_id' => $this->empresa->id, 'telefone' => '554792801006', 'status' => 'active',
            'whatsapp_channel_id' => $original->id, 'last_inbound_at' => now()->subHour(),
        ]);
        $draft = $this->draft($this->lead('(47) 99280-1006'), 'Oi!', $default);

        $this->outreach()->approve($draft);
        $message = $this->outreach()->send($draft->fresh());

        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertSame($original->id, $conversation->fresh()->whatsapp_channel_id);
        $this->assertSame('Oi!', $conversation->fresh()->last_message);
        $this->assertSame('sent', $draft->fresh()->status);
        $this->assertSame('api', OutreachAttempt::sole()->canal);
        Queue::assertPushed(SendWhatsAppMessageJob::class);
    }

    public function test_lead_who_opts_out_after_approval_is_not_messaged(): void
    {
        $this->channel($this->empresa);
        $this->template(['1' => 'lead_nome']);
        $lead = $this->lead('+55 47 99280-1006');
        $draft = $this->outreach()->approve($this->draft($lead));

        $lead->update(['opted_out_at' => now()]);
        (new SendOutreachDraftJob($draft->id))->handle($this->outreach());

        $this->assertSame('failed', $draft->fresh()->status);
        $this->assertSame('opted_out', $draft->fresh()->erro);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_skipped_draft_is_not_sent(): void
    {
        $this->channel($this->empresa);
        $this->template(['1' => 'lead_nome']);
        $draft = $this->outreach()->approve($this->draft($this->lead('+55 47 99280-1006')));

        $this->outreach()->skip($draft);
        (new SendOutreachDraftJob($draft->id))->handle($this->outreach());

        $this->assertSame('skipped', $draft->fresh()->status);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_assisted_send_is_recorded_without_using_the_api(): void
    {
        $lead = $this->lead('+55 47 99280-1006');
        $draft = $this->draft($lead);

        $attempt = $this->outreach()->markAssisted($draft, 'Oi, Carla! Tudo bem?');

        $this->assertSame('assisted', $attempt->canal);
        $this->assertSame('Oi, Carla! Tudo bem?', $attempt->mensagem);
        $this->assertSame('sent', $draft->fresh()->status);
        $this->assertSame('contatado', $lead->fresh()->status);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_reply_credits_the_last_attempt(): void
    {
        $lead = $this->lead('+55 47 99280-1006');
        $this->outreach()->markAssisted($this->draft($lead), 'Oi!');

        $this->outreach()->registerReply($lead->fresh());

        $this->assertNotNull(OutreachAttempt::sole()->responded_at);
        $this->assertSame('interessado', $lead->fresh()->status);
    }

    public function test_lead_without_probable_whatsapp_is_not_sent_through_the_api(): void
    {
        $this->channel($this->empresa);
        $this->template(['1' => 'lead_nome']);
        $lead = $this->lead('(47) 3322-1100');
        $lead->update(['enrichment_status' => 'done', 'contact_confidence' => 20]);

        $this->assertRefused('no_whatsapp', fn () => $this->outreach()->approve($this->draft($lead)));
        $this->assertSame('blocked', $this->outreach()->deliveryPlan($this->draft($lead))['mode']);
        $this->assertSame('assisted', $this->outreach()->markAssisted($this->draft($lead), 'Oi!')->canal);
    }

    public function test_auto_send_schedules_the_message_once_written(): void
    {
        $this->ai('Oi!');
        $this->empresa->update(['prospeccao_envio_automatico' => true]);
        $this->channel($this->empresa);
        $this->template(['1' => 'lead_nome']);
        $draft = $this->outreach()->request($this->lead('+55 47 99280-1006'));

        (new GenerateOutreachDraftJob($draft->id))->handle($this->outreach());

        $this->assertSame('approved', $draft->fresh()->status);
        Queue::assertPushed(SendOutreachDraftJob::class);
    }

    private function deliver(Message $message, callable $expectedParams): void
    {
        $whatsApp = Mockery::mock(WhatsAppService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $whatsApp->shouldReceive('createMessage')
            ->once()
            ->with('whatsapp:+5547992801006', Mockery::on($expectedParams))
            ->andReturn(['sid' => 'SM1', 'status' => 'queued']);

        (new SendWhatsAppMessageJob($message))->handle($whatsApp);

        $this->assertSame('sent', $message->fresh()->status);
    }

    private function assertRefused(string $reason, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected outreach to be refused with {$reason}");
        } catch (OutreachException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    private function outreach(): OutreachService
    {
        return app(OutreachService::class);
    }

    private function ai(string $text = '', bool $never = false): void
    {
        $ai = Mockery::mock(AIService::class);
        $never
            ? $ai->shouldNotReceive('gerarPrimeiraMensagemProspeccao')
            : $ai->shouldReceive('gerarPrimeiraMensagemProspeccao')->andReturn($text);
        $this->app->instance(AIService::class, $ai);
    }

    private function draft(Lead $lead, string $text = 'Oi! Vocês ainda marcam horário só pelo WhatsApp?', ?WhatsAppChannel $channel = null): OutreachDraft
    {
        return OutreachDraft::create([
            'empresa_id'          => $lead->empresa_id,
            'lead_id'             => $lead->id,
            'whatsapp_channel_id' => $channel?->id,
            'texto_final'         => $text,
            'status'              => 'draft',
        ]);
    }

    private function template(array $variaveis): WhatsAppTemplate
    {
        return WhatsAppTemplate::create([
            'empresa_id'    => $this->empresa->id,
            'nome'          => 'primeiro_contato',
            'content_sid'   => 'HX' . str_repeat('a', 32),
            'corpo_preview' => 'Olá {{1}}! {{2}}',
            'variaveis'     => $variaveis,
            'status'        => 'approved',
        ]);
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
