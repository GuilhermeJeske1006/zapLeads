<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

class TwilioWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-auth-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['twilio.token' => self::TOKEN, 'twilio.webhook_validate' => true]);
        Queue::fake();
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
