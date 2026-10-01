<?php

namespace Tests\Feature;

use App\Jobs\EnrichLeadJob;
use App\Livewire\Leads\InternetProspector;
use App\Livewire\Leads\LeadsTable;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\LeadContact;
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

    public function test_contacts_are_searched_on_demand_and_listed_with_their_origin(): void
    {
        LeadContact::create([
            'lead_id' => $this->lead->id, 'empresa_id' => $this->empresa->id, 'tipo' => 'whatsapp', 'valor' => '+5547999998888',
            'valor_e164' => '+5547999998888', 'line_type' => 'mobile', 'origem' => 'website_wa_link', 'confianca' => 95,
            'is_primary' => true, 'evidencia' => 'link de WhatsApp em https://studiobella.com.br',
        ]);

        Livewire::test(LeadsTable::class, ['empresa' => $this->empresa])
            ->call('abrirModal', $this->lead->id)
            ->assertSee('link de WhatsApp em https://studiobella.com.br')
            ->assertSee(__('messages.contact_origin_website_wa_link'))
            ->call('buscarContatos', $this->lead->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('pending', $this->lead->fresh()->enrichment_status);
        Queue::assertPushedOn('enrichment', EnrichLeadJob::class);
    }

    public function test_prospector_list_follows_enrichment_in_the_background(): void
    {
        $this->lead->update(['enrichment_status' => 'pending']);
        $component = Livewire::test(InternetProspector::class, ['empresa' => $this->empresa])
            ->set('resultados', [$this->lead->fresh()->toArray()]);

        $this->lead->update(['enrichment_status' => 'done', 'contact_confidence' => 20, 'lead_score' => 47, 'ai_insights' => ['gancho' => 'Nota 4,9']]);
        $component->call('pollSearch');

        $row = $component->get('resultados')[0];
        $this->assertSame(['done', 20, 47, 'Nota 4,9'], [$row['enrichment_status'], $row['contact_confidence'], $row['lead_score'], $row['ai_insights']['gancho']]);
        $component->assertSee(__('messages.no_whatsapp_call'))
            ->call('abrirModal', $this->lead->id)
            ->assertSee(__('messages.score_breakdown_title'))
            ->assertSee('Nota 4,9');
    }

    private function channel(string $numero, bool $isDefault = false): WhatsAppChannel
    {
        return WhatsAppChannel::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Vendas', 'numero' => $numero, 'is_default' => $isDefault, 'ativo' => true,
        ]);
    }
}
