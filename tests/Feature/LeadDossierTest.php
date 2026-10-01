<?php

namespace Tests\Feature;

use App\Jobs\EnrichLeadJob;
use App\Jobs\GenerateOutreachDraftJob;
use App\Livewire\Leads\LeadDossier;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\OutreachAttempt;
use App\Models\OutreachDraft;
use App\Models\ProspectingSearch;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\User;
use App\Services\Prospecting\FollowUpCadence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class LeadDossierTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);
        $this->lead = Lead::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Studio Bella', 'telefone' => '(47) 99280-1006', 'source' => 'internet',
            'decisor_nome' => 'Carla Souza', 'decisor_cargo' => 'Sócia-administradora', 'porte' => 'ME',
            'ai_insights' => ['rating' => 4.8, 'user_ratings_total' => 212],
            'dossie' => ['reviews' => [['nota' => 2, 'texto' => 'Demoram para responder no WhatsApp', 'quando' => 'há 2 semanas']]],
        ]);
    }

    public function test_opens_only_the_empresa_leads(): void
    {
        $other = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Outra']);
        $foreign = Lead::create(['empresa_id' => $other->id, 'nome' => 'Lead alheio', 'telefone' => '']);

        Livewire::test(LeadDossier::class, ['empresa' => $this->empresa])
            ->dispatch('open-lead-dossier', id: $foreign->id)
            ->assertSet('leadId', null)
            ->assertDontSee('Lead alheio')
            ->dispatch('open-lead-dossier', id: $this->lead->id)
            ->assertSet('leadId', $this->lead->id)
            ->assertSee('Studio Bella')
            ->assertSee('Carla Souza')
            ->assertSee('Demoram para responder no WhatsApp')
            ->call('fechar')
            ->assertSet('leadId', null);
    }

    public function test_lead_id_cannot_be_set_from_the_browser(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::test(LeadDossier::class, ['empresa' => $this->empresa])->set('leadId', $this->lead->id);
    }

    public function test_contacts_show_where_they_came_from_and_can_be_searched_again(): void
    {
        LeadContact::create([
            'lead_id' => $this->lead->id, 'empresa_id' => $this->empresa->id, 'tipo' => 'whatsapp', 'valor' => '+5547999998888',
            'valor_e164' => '+5547999998888', 'line_type' => 'mobile', 'origem' => 'website_wa_link', 'confianca' => 95,
            'is_primary' => true, 'evidencia' => 'link de WhatsApp em https://studiobella.com.br',
        ]);
        LeadContact::create([
            'lead_id' => $this->lead->id, 'empresa_id' => $this->empresa->id, 'tipo' => 'telefone', 'valor' => '+554733221100',
            'valor_e164' => '+554733221100', 'line_type' => 'fixed', 'origem' => 'google_places', 'confianca' => 20, 'evidencia' => 'Google Maps',
        ]);
        $this->lead->update(['enrichment_status' => 'done', 'contact_confidence' => 95]);

        Livewire::test(LeadDossier::class, ['empresa' => $this->empresa])
            ->call('abrir', $this->lead->id)
            ->assertSee('link de WhatsApp em https://studiobella.com.br')
            ->assertSee(__('messages.contact_origin_label', ['origem' => __('messages.contact_origin_website_wa_link')]))
            ->assertSee('(47) 99999-8888')
            ->assertSeeHtml('href="https://wa.me/5547999998888"')
            ->assertSeeHtml('href="tel:+554733221100"')
            ->call('buscarContatos')
            ->assertDispatched('lead-updated')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('pending', $this->lead->fresh()->enrichment_status);
        Queue::assertPushedOn('enrichment', EnrichLeadJob::class);
    }

    public function test_reply_to_an_assisted_message_is_registered(): void
    {
        OutreachAttempt::create(['empresa_id' => $this->empresa->id, 'lead_id' => $this->lead->id, 'canal' => 'assisted', 'mensagem' => 'Oi!']);
        $this->lead->update(['status' => 'abordado']);

        Livewire::test(LeadDossier::class, ['empresa' => $this->empresa])
            ->call('abrir', $this->lead->id)
            ->assertSee(__('messages.lead_replied'))
            ->call('registrarResposta')
            ->assertDispatched('lead-updated');

        $this->assertNotNull(OutreachAttempt::sole()->responded_at);
        $this->assertSame('respondeu', $this->lead->fresh()->status);
    }

    public function test_moving_past_approached_ends_the_cold_cadence(): void
    {
        $sequence = Sequence::create(['empresa_id' => $this->empresa->id, 'nome' => 'Prospecção fria', 'trigger' => FollowUpCadence::TRIGGER, 'status' => 'active']);
        $enrollment = SequenceEnrollment::create(['sequence_id' => $sequence->id, 'lead_id' => $this->lead->id, 'current_step' => 1, 'status' => 'active', 'next_send_at' => now()]);
        $followUp = OutreachDraft::create(['empresa_id' => $this->empresa->id, 'lead_id' => $this->lead->id, 'etapa' => 1, 'status' => 'draft', 'texto_final' => 'Oi de novo']);

        Livewire::test(LeadDossier::class, ['empresa' => $this->empresa])
            ->call('abrir', $this->lead->id)
            ->call('alterarStatus', 'invalido')
            ->call('alterarStatus', 'reuniao')
            ->assertDispatched('lead-updated');

        $this->assertSame('reuniao', $this->lead->fresh()->status);
        $this->assertSame('stopped', $enrollment->fresh()->status);
        $this->assertSame('skipped', $followUp->fresh()->status);
    }

    public function test_suggested_messages_can_be_written_and_switched(): void
    {
        $component = Livewire::test(LeadDossier::class, ['empresa' => $this->empresa])
            ->call('abrir', $this->lead->id)
            ->assertSee(__('messages.suggested_messages_empty'))
            ->call('gerarAbordagem')
            ->assertDispatched('outreach-requested');

        Queue::assertPushed(GenerateOutreachDraftJob::class);
        $draft = OutreachDraft::sole();
        $draft->update([
            'status' => 'draft', 'variante_escolhida' => 'observacao', 'texto_final' => 'Oi, Carla! Vi a nota 4,8 de vocês.',
            'variantes' => [
                ['angulo' => 'observacao', 'mensagem' => 'Oi, Carla! Vi a nota 4,8 de vocês.', 'gancho_usado' => 'nota 4,8'],
                ['angulo' => 'roteamento', 'mensagem' => 'Oi! É com você que falo sobre a agenda?', 'gancho_usado' => null],
            ],
        ]);

        $component->call('escolherVariante', 'roteamento')
            ->assertSee('Oi! É com você que falo sobre a agenda?')
            ->assertSee(__('messages.edit_and_send_in_queue'));
    }

    public function test_timeline_tells_what_happened_to_the_lead(): void
    {
        $search = ProspectingSearch::create([
            'empresa_id' => $this->empresa->id, 'descricao_empresa' => 'Agenda', 'tipo_cliente' => 'Salões de beleza',
            'latitude' => 0, 'longitude' => 0, 'radius_km' => 5, 'status' => 'done',
        ]);
        $this->lead->update(['prospecting_search_id' => $search->id, 'enriched_at' => now()]);
        OutreachAttempt::create(['empresa_id' => $this->empresa->id, 'lead_id' => $this->lead->id, 'canal' => 'assisted', 'mensagem' => 'Oi!', 'responded_at' => now()->addMinute()]);

        Livewire::test(LeadDossier::class, ['empresa' => $this->empresa])
            ->call('abrir', $this->lead->id)
            ->assertSee(__('messages.timeline_found', ['busca' => 'Salões de beleza']))
            ->assertSee(__('messages.timeline_enriched'))
            ->assertSee(__('messages.timeline_approached', ['canal' => __('messages.timeline_channel_assisted'), 'etapa' => 0]))
            ->assertSee(__('messages.timeline_replied'));
    }

    public function test_links_typed_by_users_only_open_as_http(): void
    {
        $this->lead->update(['website' => 'javascript:alert(1)']);

        Livewire::test(LeadDossier::class, ['empresa' => $this->empresa])
            ->call('abrir', $this->lead->id)
            ->assertDontSeeHtml('href="javascript:alert(1)"');
    }
}
