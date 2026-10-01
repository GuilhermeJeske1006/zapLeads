<?php

namespace Tests\Feature;

use App\Livewire\Leads\LeadsTable;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachAttempt;
use App\Models\OutreachDraft;
use App\Models\User;
use App\Models\WhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class LeadsTableOutreachTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);
        $this->lead = Lead::create(['empresa_id' => $this->empresa->id, 'nome' => 'Studio Bella', 'telefone' => '(47) 99280-1006']);
    }

    public function test_lead_can_be_approached_without_an_api_channel(): void
    {
        Livewire::test(LeadsTable::class, ['empresa' => $this->empresa])
            ->call('gerarAbordagem', $this->lead->id)
            ->assertSet('showSelectChannelModal', false)
            ->assertDispatched('outreach-requested');

        $this->assertNull(OutreachDraft::sole()->whatsapp_channel_id);
    }

    public function test_with_several_channels_the_user_picks_one(): void
    {
        $this->channel('whatsapp:+5547900000001', isDefault: true);
        $other = $this->channel('whatsapp:+5547900000002');

        Livewire::test(LeadsTable::class, ['empresa' => $this->empresa])
            ->call('gerarAbordagem', $this->lead->id)
            ->assertSet('showSelectChannelModal', true)
            ->set('selectedChannelId', $other->id)
            ->call('confirmarCanal')
            ->assertSet('showSelectChannelModal', false);

        $this->assertSame($other->id, OutreachDraft::sole()->whatsapp_channel_id);
    }

    public function test_reply_to_an_assisted_message_is_registered(): void
    {
        OutreachAttempt::create(['empresa_id' => $this->empresa->id, 'lead_id' => $this->lead->id, 'canal' => 'assisted', 'mensagem' => 'Oi!']);
        $this->lead->update(['status' => 'contatado']);

        Livewire::test(LeadsTable::class, ['empresa' => $this->empresa])
            ->call('abrirModal', $this->lead->id)
            ->assertSee(__('messages.lead_replied'))
            ->call('registrarResposta', $this->lead->id);

        $this->assertNotNull(OutreachAttempt::sole()->responded_at);
        $this->assertSame('interessado', $this->lead->fresh()->status);
    }

    private function channel(string $numero, bool $isDefault = false): WhatsAppChannel
    {
        return WhatsAppChannel::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Vendas', 'numero' => $numero, 'is_default' => $isDefault, 'ativo' => true,
        ]);
    }
}
