<?php

namespace Tests\Feature;

use App\Livewire\Leads\InternetProspector;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class InternetProspectorSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_whatsapp_channel_before_generating_message(): void
    {
        [$empresa, $lead] = $this->empresaWithLead();

        Livewire::test(InternetProspector::class, ['empresa' => $empresa])
            ->call('enviarMensagemIA', $lead->id)
            ->assertDispatched('toast', type: 'error', message: __('messages.whatsapp_channel_required'));

        $this->assertDatabaseCount('conversations', 0);
        Queue::assertNothingPushed();
    }

    public function test_does_not_message_opted_out_lead(): void
    {
        [$empresa, $lead] = $this->empresaWithLead();
        $lead->update(['opted_out_at' => now()]);
        $empresa->whatsappChannels()->create(['nome' => 'Vendas', 'numero' => 'whatsapp:+5547900000001', 'is_default' => true, 'ativo' => true]);

        Livewire::test(InternetProspector::class, ['empresa' => $empresa])
            ->call('enviarMensagemIA', $lead->id)
            ->assertDispatched('toast', type: 'error', message: __('messages.lead_opted_out'));

        $this->assertDatabaseCount('conversations', 0);
        Queue::assertNothingPushed();
    }

    public function test_does_not_message_lead_with_unreadable_phone(): void
    {
        [$empresa, $lead] = $this->empresaWithLead();
        $lead->update(['telefone' => '9280-1006']);
        $empresa->whatsappChannels()->create(['nome' => 'Vendas', 'numero' => 'whatsapp:+5547900000001', 'is_default' => true, 'ativo' => true]);

        Livewire::test(InternetProspector::class, ['empresa' => $empresa])
            ->call('enviarMensagemIA', $lead->id)
            ->assertDispatched('toast', type: 'error', message: __('messages.lead_invalid_phone'));

        $this->assertDatabaseCount('conversations', 0);
    }

    /** @return array{Empresa, Lead} */
    private function empresaWithLead(): array
    {
        Queue::fake();

        $ai = Mockery::mock(AIService::class);
        $ai->shouldNotReceive('gerarPrimeiraMensagemProspeccao');
        $this->app->instance(AIService::class, $ai);

        $empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Empresa']);
        $lead = Lead::create(['empresa_id' => $empresa->id, 'nome' => 'Studio Bella', 'telefone' => '+55 47 99691-8841']);

        return [$empresa, $lead];
    }
}
