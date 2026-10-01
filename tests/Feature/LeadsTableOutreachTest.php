<?php

namespace Tests\Feature;

use App\Livewire\Leads\LeadsTable;
use App\Models\Empresa;
use App\Models\Lead;
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

    public function test_status_change_from_the_list_uses_the_funnel(): void
    {
        Livewire::test(LeadsTable::class, ['empresa' => $this->empresa])
            ->call('alterarStatus', $this->lead->id, 'contatado')
            ->assertNotDispatched('toast')
            ->call('alterarStatus', $this->lead->id, 'proposta')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('proposta', $this->lead->fresh()->status);
    }

    public function test_list_filters_by_funnel_stage_and_origin_and_opens_the_dossier(): void
    {
        Lead::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cliente do catalogo', 'telefone' => '', 'source' => 'internal', 'status' => 'respondeu']);

        Livewire::test(LeadsTable::class, ['empresa' => $this->empresa])
            ->assertSeeHtml("\$dispatch('open-lead-dossier', { id: {$this->lead->id} })")
            ->set('filterStatus', 'respondeu')
            ->assertSee('Cliente do catalogo')
            ->assertDontSee('Studio Bella')
            ->set('filterStatus', '')
            ->set('filterSource', 'internet')
            ->assertDontSee('Cliente do catalogo');
    }

    public function test_drafts_waiting_review_link_to_the_review_step(): void
    {
        OutreachDraft::create(['empresa_id' => $this->empresa->id, 'lead_id' => $this->lead->id, 'status' => 'draft', 'texto_final' => 'Oi!']);

        Livewire::test(LeadsTable::class, ['empresa' => $this->empresa])
            ->assertSeeHtml('href="' . e(route('prospeccao.index', ['passo' => 'abordagens'])) . '"');
    }

    private function channel(string $numero, bool $isDefault = false): WhatsAppChannel
    {
        return WhatsAppChannel::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Vendas', 'numero' => $numero, 'is_default' => $isDefault, 'ativo' => true,
        ]);
    }
}
