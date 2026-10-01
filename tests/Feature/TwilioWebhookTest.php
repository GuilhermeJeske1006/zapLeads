<?php

namespace Tests\Feature;

use App\Jobs\AutoRespondJob;
use App\Models\Conversation;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\Message;
use App\Models\OutreachAttempt;
use App\Models\User;
use App\Models\WhatsAppChannel;
use App\Services\WhatsAppService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

class TwilioWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-auth-token';

    private MockInterface $whatsApp;

    protected function setUp(): void
    {
        parent::setUp();

        config(['twilio.token' => self::TOKEN, 'twilio.webhook_validate' => true]);
        Queue::fake();

        $this->whatsApp = Mockery::mock(WhatsAppService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->whatsApp->shouldReceive('createMessage')->andReturn(['sid' => 'SMconfirm', 'status' => 'queued'])->byDefault();
        $this->app->instance(WhatsAppService::class, $this->whatsApp);
    }

    public function test_rejects_request_without_signature(): void
    {
        $this->channel($this->empresa(), 'whatsapp:+5547900000001');

        $this->post(route('webhook.twilio'), $this->inbound('whatsapp:+5547900000001'))
            ->assertForbidden();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_rejects_request_with_invalid_signature(): void
    {
        $this->channel($this->empresa(), 'whatsapp:+5547900000001');

        $this->post(route('webhook.twilio'), $this->inbound('whatsapp:+5547900000001'), ['X-Twilio-Signature' => 'forged'])
            ->assertForbidden();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_signed_message_goes_to_empresa_that_owns_the_number(): void
    {
        $this->channel($this->empresa(), 'whatsapp:+5547900000001');
        $empresa = $this->empresa();
        $channel = $this->channel($empresa, 'whatsapp:+5547900000002');

        $this->signedPost($this->inbound('whatsapp:+5547900000002'))->assertNoContent();

        $conversation = Conversation::sole();
        $this->assertSame($empresa->id, $conversation->empresa_id);
        $this->assertSame($channel->id, $conversation->whatsapp_channel_id);
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_message_to_number_without_channel_is_dropped(): void
    {
        $this->channel($this->empresa(), 'whatsapp:+5547900000001');

        $this->signedPost($this->inbound('whatsapp:+5547900000099'))->assertNoContent();

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_shared_number_goes_to_empresa_already_talking_to_contact(): void
    {
        $empresaA = $this->empresa();
        $empresaB = $this->empresa();
        $this->channel($empresaA, 'whatsapp:+14155238886');
        $this->channel($empresaB, 'whatsapp:+14155238886');
        Conversation::create([
            'empresa_id'      => $empresaB->id,
            'telefone'        => '5547911112222',
            'status'          => 'active',
            'last_message_at' => now(),
        ]);

        $this->signedPost($this->inbound('whatsapp:+14155238886'))->assertNoContent();

        $this->assertSame(0, Conversation::where('empresa_id', $empresaA->id)->count());
        $this->assertSame(1, Conversation::where('empresa_id', $empresaB->id)->sole()->messages()->count());
    }

    public function test_shared_number_without_prior_conversation_is_dropped(): void
    {
        $this->channel($this->empresa(), 'whatsapp:+14155238886');
        $this->channel($this->empresa(), 'whatsapp:+14155238886');

        $this->signedPost($this->inbound('whatsapp:+14155238886'))->assertNoContent();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_message_without_ninth_digit_joins_existing_conversation(): void
    {
        $empresa = $this->empresa();
        $channel = $this->channel($empresa, 'whatsapp:+5547900000001');
        $conversation = Conversation::create([
            'empresa_id'          => $empresa->id,
            'telefone'            => '+55 47 99280-1006',
            'whatsapp_channel_id' => $channel->id,
            'status'              => 'active',
        ]);

        $this->signedPost($this->inbound('whatsapp:+5547900000001', from: 'whatsapp:+554792801006'))->assertNoContent();

        $this->assertSame($conversation->id, Conversation::sole()->id);
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_opt_out_matches_lead_saved_in_another_format(): void
    {
        $empresa = $this->empresa();
        $this->channel($empresa, 'whatsapp:+5547900000001');
        $lead = Lead::create(['empresa_id' => $empresa->id, 'nome' => 'Studio Bella', 'telefone' => '+55 47 99280-1006']);

        $this->signedPost($this->inbound('whatsapp:+5547900000001', from: 'whatsapp:+554792801006', body: 'Sair'))
            ->assertNoContent();

        $this->assertTrue($lead->fresh()->isOptedOut());
        $this->assertSame('blocked', Conversation::sole()->status);
    }

    public function test_opt_out_is_confirmed_once_and_stops_the_bot(): void
    {
        $empresa = $this->empresa();
        $empresa->update(['bot_ativo' => true]);
        $this->channel($empresa, 'whatsapp:+5547900000001');
        Lead::create(['empresa_id' => $empresa->id, 'nome' => 'Studio Bella', 'telefone' => '+55 47 99280-1006']);
        $this->whatsApp->shouldReceive('createMessage')
            ->once()
            ->with('whatsapp:+5547992801006', Mockery::on(fn (array $params) => $params['body'] === __('messages.opt_out_confirmation')))
            ->andReturn(['sid' => 'SMconfirm', 'status' => 'queued']);

        $this->signedPost($this->inbound('whatsapp:+5547900000001', from: 'whatsapp:+5547992801006', body: 'PARE!'))->assertNoContent();
        $this->signedPost($this->inbound('whatsapp:+5547900000001', from: 'whatsapp:+5547992801006', body: 'Sair'))->assertNoContent();

        $confirmation = Message::where('sender', 'user')->sole();
        $this->assertSame('sent', $confirmation->status);
        $this->assertSame('SMconfirm', $confirmation->twilio_message_sid);
        Queue::assertNotPushed(AutoRespondJob::class);
    }

    public function test_contact_without_lead_can_opt_out(): void
    {
        $empresa = $this->empresa();
        $this->channel($empresa, 'whatsapp:+5547900000001');

        $this->signedPost($this->inbound('whatsapp:+5547900000001', body: 'Não quero'))->assertNoContent();

        $this->assertSame('blocked', Conversation::sole()->status);
    }

    public function test_inbound_message_opens_the_session_and_credits_the_last_outreach(): void
    {
        $empresa = $this->empresa();
        $this->channel($empresa, 'whatsapp:+5547900000001');
        $lead = Lead::create(['empresa_id' => $empresa->id, 'nome' => 'Studio Bella', 'telefone' => '(47) 91111-2222']);
        $older = OutreachAttempt::create(['empresa_id' => $empresa->id, 'lead_id' => $lead->id, 'canal' => 'assisted', 'mensagem' => 'Oi!']);
        $latest = OutreachAttempt::create(['empresa_id' => $empresa->id, 'lead_id' => $lead->id, 'canal' => 'api', 'mensagem' => 'Oi de novo!']);

        $this->signedPost($this->inbound('whatsapp:+5547900000001'))->assertNoContent();

        $this->assertTrue(Conversation::sole()->isSessionOpen());
        $this->assertNull($older->fresh()->responded_at);
        $this->assertNotNull($latest->fresh()->responded_at);
    }

    public function test_delivery_error_is_stored_on_the_message(): void
    {
        $empresa = $this->empresa();
        $this->channel($empresa, 'whatsapp:+5547900000001');
        $conversation = Conversation::create(['empresa_id' => $empresa->id, 'telefone' => '5547911112222', 'status' => 'active']);
        $message = Message::create([
            'conversation_id' => $conversation->id, 'sender' => 'user', 'message' => 'Oi!', 'status' => 'sent', 'twilio_message_sid' => 'SM123',
        ]);

        $this->signedPost(['MessageSid' => 'SM123', 'MessageStatus' => 'undelivered', 'ErrorCode' => '63016'])->assertNoContent();

        $this->assertSame('failed', $message->fresh()->status);
        $this->assertSame('63016', $message->fresh()->error_code);
        $this->assertSame(__('messages.error_63016'), $message->fresh()->failureReason());
    }

    public function test_bot_hours_follow_the_empresa_timezone(): void
    {
        $empresa = $this->empresa();
        $empresa->update(['bot_ativo' => true, 'bot_horario_inicio' => '09:00:00', 'bot_horario_fim' => '18:00:00', 'timezone' => 'America/Sao_Paulo']);
        $this->channel($empresa, 'whatsapp:+5547900000001');

        $this->travelTo(CarbonImmutable::parse('2026-10-06 17:00', 'America/Sao_Paulo')); // 20:00 UTC
        $this->signedPost($this->inbound('whatsapp:+5547900000001'))->assertNoContent();
        Queue::assertPushed(AutoRespondJob::class, 1);

        $this->travelTo(CarbonImmutable::parse('2026-10-06 19:00', 'America/Sao_Paulo'));
        $this->signedPost($this->inbound('whatsapp:+5547900000001'))->assertNoContent();
        Queue::assertPushed(AutoRespondJob::class, 1);
    }

    private function signedPost(array $params): TestResponse
    {
        $url = route('webhook.twilio');
        $signature = (new RequestValidator(self::TOKEN))->computeSignature($url, $params);

        return $this->post($url, $params, ['X-Twilio-Signature' => $signature]);
    }

    private function inbound(string $to, string $from = 'whatsapp:+5547911112222', string $body = 'Oi, tudo bem?'): array
    {
        return [
            'From'        => $from,
            'To'          => $to,
            'Body'        => $body,
            'MessageSid'  => 'SM' . Str::random(32),
            'ProfileName' => 'Cliente',
        ];
    }

    private function empresa(): Empresa
    {
        return Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Empresa']);
    }

    private function channel(Empresa $empresa, string $numero): WhatsAppChannel
    {
        return WhatsAppChannel::create([
            'empresa_id' => $empresa->id,
            'nome'       => 'Vendas',
            'numero'     => $numero,
            'is_default' => true,
            'ativo'      => true,
        ]);
    }
}
