<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppChannel;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsAppServiceTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_opt_out_in_one_empresa_does_not_block_another(): void
    {
        $empresaA = $this->empresa();
        $empresaB = $this->empresa();
        $channelB = $this->channel($empresaB, 'whatsapp:+5547900000002');
        $this->optedOutLead($empresaA, '5547911112222');

        $result = $this->serviceExpectingSends(1)->sendTextMessage('5547911112222', 'Oi', $empresaB->id, $channelB);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('message_logs', ['empresa_id' => $empresaB->id, 'status' => 'success']);
    }

    public function test_opted_out_lead_is_not_messaged_by_its_own_empresa(): void
    {
        $empresa = $this->empresa();
        $channel = $this->channel($empresa, 'whatsapp:+5547900000001');
        $this->optedOutLead($empresa, '5547911112222');

        $result = $this->serviceExpectingSends(0)->sendTextMessage('5547911112222', 'Oi', $empresa->id, $channel);

        $this->assertSame('opted_out', $result['error']);
    }

    public function test_opted_out_lead_does_not_receive_images(): void
    {
        $empresa = $this->empresa();
        $channel = $this->channel($empresa, 'whatsapp:+5547900000001');
        $this->optedOutLead($empresa, '5547911112222');

        $result = $this->serviceExpectingSends(0)
            ->sendImageMessage('5547911112222', 'https://example.com/foto.jpg', '', $empresa->id, $channel);

        $this->assertSame('opted_out', $result['error']);
    }

    public function test_empresa_without_channel_does_not_send(): void
    {
        $empresa = $this->empresa();

        $result = $this->serviceExpectingSends(0)->sendTextMessage('5547911112222', 'Oi', $empresa->id);

        $this->assertSame('no_channel', $result['error']);
    }

    public function test_channel_of_another_empresa_is_refused(): void
    {
        $empresa = $this->empresa();
        $otherChannel = $this->channel($this->empresa(), 'whatsapp:+5547900000002');

        $result = $this->serviceExpectingSends(0)->sendTextMessage('5547911112222', 'Oi', $empresa->id, $otherChannel);

        $this->assertSame('no_channel', $result['error']);
    }

    public function test_sends_from_empresa_default_channel_when_none_given(): void
    {
        $empresa = $this->empresa();
        $this->channel($empresa, 'whatsapp:+5547900000001', isDefault: false);
        $this->channel($empresa, 'whatsapp:+5547900000009', isDefault: true);

        $service = Mockery::mock(WhatsAppService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('createMessage')
            ->once()
            ->with('whatsapp:+5547911112222', Mockery::on(fn (array $params) => $params['from'] === 'whatsapp:+5547900000009'))
            ->andReturn(['sid' => 'SM1', 'status' => 'queued']);

        $result = $service->sendTextMessage('5547911112222', 'Oi', $empresa->id);

        $this->assertTrue($result['success']);
    }

    public function test_opt_out_matches_lead_saved_in_another_format(): void
    {
        $empresa = $this->empresa();
        $channel = $this->channel($empresa, 'whatsapp:+5547900000001');
        $this->optedOutLead($empresa, '+55 47 99280-1006');

        $result = $this->serviceExpectingSends(0)->sendTextMessage('554792801006', 'Oi', $empresa->id, $channel);

        $this->assertSame('opted_out', $result['error']);
    }

    public function test_sends_to_canonical_number(): void
    {
        $empresa = $this->empresa();
        $channel = $this->channel($empresa, 'whatsapp:+5547900000001');

        $service = Mockery::mock(WhatsAppService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('createMessage')
            ->once()
            ->with('whatsapp:+5547992801006', Mockery::type('array'))
            ->andReturn(['sid' => 'SM1', 'status' => 'queued']);

        $this->assertTrue($service->sendTextMessage('(47) 99280-1006', 'Oi', $empresa->id, $channel)['success']);
    }

    public function test_unreadable_phone_is_not_sent(): void
    {
        $empresa = $this->empresa();
        $channel = $this->channel($empresa, 'whatsapp:+5547900000001');

        $result = $this->serviceExpectingSends(0)->sendTextMessage('99280-10', 'Oi', $empresa->id, $channel);

        $this->assertSame('invalid_phone', $result['error']);
    }

    private function serviceExpectingSends(int $times): WhatsAppService
    {
        $service = Mockery::mock(WhatsAppService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('createMessage')->times($times)->andReturn(['sid' => 'SM1', 'status' => 'queued']);

        return $service;
    }

    private function optedOutLead(Empresa $empresa, string $telefone): Lead
    {
        return Lead::create([
            'empresa_id'   => $empresa->id,
            'nome'         => 'Cliente',
            'telefone'     => $telefone,
            'opted_out_at' => now(),
        ]);
    }

    private function empresa(): Empresa
    {
        return Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Empresa']);
    }

    private function channel(Empresa $empresa, string $numero, bool $isDefault = true): WhatsAppChannel
    {
        return WhatsAppChannel::create([
            'empresa_id' => $empresa->id,
            'nome'       => 'Vendas',
            'numero'     => $numero,
            'is_default' => $isDefault,
            'ativo'      => true,
        ]);
    }
}
