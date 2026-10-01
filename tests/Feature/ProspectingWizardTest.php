<?php

namespace Tests\Feature;

use App\Jobs\FindInternetLeadsJob;
use App\Jobs\GenerateOutreachDraftJob;
use App\Livewire\Prospecting\ProspectingWizard;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\ProspectingSearch;
use App\Models\User;
use App\Services\Geo\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProspectingWizardTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->empresa = Empresa::create([
            'user_id'           => User::factory()->create()->id,
            'nome'              => 'Agenda Fácil',
            'endereco'          => 'Rua XV de Novembro, 100',
            'cidade'            => 'Blumenau',
            'latitude'          => -26.9194,
            'longitude'         => -49.0661,
            'raio_atendimento'  => 7,
            'descricao_empresa' => 'Sistema de agendamento online para salões e clínicas.',
            'tipo_cliente_alvo' => 'Salões de beleza, barbearias',
        ]);
    }

    public function test_profile_comes_from_the_empresa(): void
    {
        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->assertSet('passo', 'perfil')
            ->assertSet('tiposCliente', ['Salões de beleza', 'barbearias'])
            ->assertSet('descricaoEmpresa', 'Sistema de agendamento online para salões e clínicas.')
            ->assertSet('raioBuscaKm', 7.0)
            ->assertSet('onde', 'empresa')
            ->assertSee('Rua XV de Novembro, 100')
            ->assertDontSeeHtml('wire:model="endereco"');
    }

    public function test_search_near_the_empresa_runs_in_the_background_and_leaves_its_profile_alone(): void
    {
        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->set('tiposCliente', ['Clínicas de estética'])
            ->set('raioBuscaKm', 3)
            ->set('semSite', true)
            ->call('buscar')
            ->assertHasNoErrors()
            ->assertSet('passo', 'resultados');

        $search = ProspectingSearch::sole();
        $this->assertSame(['queued', 'Clínicas de estética', 3.0, null], [$search->status, $search->tipo_cliente, $search->radius_km, $search->local_label]);
        $this->assertSame([-26.9194, -49.0661], [$search->latitude, $search->longitude]);
        $this->assertSame(['so_whatsapp' => true, 'sem_site' => true, 'nota_min' => null], $search->filtros);
        Queue::assertPushed(FindInternetLeadsJob::class, fn (FindInternetLeadsJob $job) => $job->searchId === $search->id && $job->customLat === null);

        // The ICP and the address stay as they were in Minha Empresa.
        $this->empresa->refresh();
        $this->assertSame(['Salões de beleza, barbearias', 'Rua XV de Novembro, 100', 7.0], [$this->empresa->tipo_cliente_alvo, $this->empresa->endereco, (float) $this->empresa->raio_atendimento]);
    }

    public function test_first_search_fills_an_empty_sales_profile(): void
    {
        $this->empresa->update(['descricao_empresa' => null, 'tipo_cliente_alvo' => null]);

        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->set('descricaoEmpresa', 'Fornecedor de embalagens para alimentos')
            ->set('tiposCliente', ['Padarias', ' confeitarias ', 'Padarias'])
            ->call('buscar')
            ->assertHasNoErrors();

        $this->assertSame('Padarias, confeitarias', $this->empresa->fresh()->tipo_cliente_alvo);
        $this->assertSame('Fornecedor de embalagens para alimentos', $this->empresa->fresh()->descricao_empresa);
    }

    public function test_search_needs_a_customer_type_and_a_place(): void
    {
        $this->empresa->update(['latitude' => null, 'longitude' => null]);

        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->assertSet('onde', 'outro')
            ->set('tiposCliente', [])
            ->call('buscar')
            ->assertHasErrors(['tiposCliente', 'localBusca'])
            ->set('onde', 'empresa')
            ->set('tiposCliente', ['Padarias'])
            ->call('buscar')
            ->assertHasErrors(['onde']);

        $this->assertDatabaseCount('prospecting_searches', 0);
        Queue::assertNothingPushed();
    }

    public function test_place_typed_by_hand_is_geocoded_near_the_empresa_city(): void
    {
        $geo = Mockery::mock(GeocodingService::class);
        $geo->shouldReceive('geocode')->once()->with('Rua das Palmeiras Blumenau')->andReturn([
            'lat' => -26.93, 'lng' => -49.05, 'display_name' => 'Rua das Palmeiras, Blumenau - SC, Brasil',
        ]);
        $this->app->instance(GeocodingService::class, $geo);

        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->set('onde', 'outro')
            ->set('localBusca', 'Rua das Palmeiras')
            ->call('buscar')
            ->assertHasNoErrors();

        $this->assertSame('Rua das Palmeiras, Blumenau - SC, Brasil', ProspectingSearch::sole()->local_label);
        Queue::assertPushed(FindInternetLeadsJob::class, fn (FindInternetLeadsJob $job) => $job->customLat === -26.93
            && $job->customLng === -49.05
            && $job->customLocationLabel === 'Rua das Palmeiras, Blumenau - SC, Brasil');
    }

    public function test_point_picked_on_the_map_is_used_without_geocoding(): void
    {
        $geo = Mockery::mock(GeocodingService::class);
        $geo->shouldNotReceive('geocode');
        $this->app->instance(GeocodingService::class, $geo);

        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->dispatch('prospecting-map:location', lat: -26.304408, lng: -48.848111, label: 'Joinville - SC, Brasil')
            ->assertSet('onde', 'outro')
            ->call('buscar')
            ->assertHasNoErrors();

        Queue::assertPushed(FindInternetLeadsJob::class, fn (FindInternetLeadsJob $job) => $job->customLat === -26.304408
            && $job->customLng === -48.848111
            && $job->customLocationLabel === 'Joinville - SC, Brasil');
    }

    public function test_results_follow_the_search_and_keep_their_order_while_scores_change(): void
    {
        $component = Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])->call('buscar');
        $search = ProspectingSearch::sole();
        $component->assertSeeHtml('wire:poll.3s="atualizar"');

        $low = $this->lead($search, 'Barbearia Central', ['lead_score' => 40]);
        $high = $this->lead($search, 'Studio Bella', ['lead_score' => 80]);
        $search->update(['status' => 'done', 'stage' => 'enriching', 'progress' => ['keywords' => 5, 'empresas' => 2, 'enriquecer' => 2]]);

        $component->call('atualizar')
            ->assertSet('ordem', [$high->id, $low->id])
            ->assertSeeInOrder(['Studio Bella', 'Barbearia Central'])
            ->assertSee(trans_choice('messages.progress_searching_done', 2, ['count' => 2]));

        // Contacts found raise the other lead's score: the rows stay put until the user asks.
        $low->update(['lead_score' => 95]);
        $component->call('atualizar')->assertSeeInOrder(['Studio Bella', 'Barbearia Central'])
            ->call('ordenarPorScore')->assertSeeInOrder(['Barbearia Central', 'Studio Bella']);

        $search->update(['stage' => 'done']);
        $component->call('atualizar')->assertDontSeeHtml('wire:poll.3s="atualizar"');
    }

    public function test_results_list_follows_contacts_found_in_the_background(): void
    {
        $search = $this->search();
        $lead = $this->lead($search, 'Studio Bella', ['enrichment_status' => 'pending']);

        $component = Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->call('abrirBusca', $search->id)
            ->set('soWhatsApp', false)
            ->assertSee(__('messages.enrichment_running'));

        $lead->update(['enrichment_status' => 'done', 'contact_confidence' => 20, 'ai_insights' => ['gancho' => 'Nota 4,9 com 212 avaliações']]);

        $component->call('atualizar')
            ->assertSee(__('messages.no_whatsapp_call'))
            ->assertSee('Nota 4,9 com 212 avaliações');
    }

    public function test_filters_hide_leads_without_probable_whatsapp_with_a_site_or_low_rating(): void
    {
        $search = $this->search();
        $this->lead($search, 'Celular sem site', ['telefone' => '(47) 99280-1006', 'ai_insights' => ['rating' => 4.7]]);
        $this->lead($search, 'Fixo do Google', ['telefone' => '(47) 3322-1100', 'ai_insights' => ['rating' => 4.9]]);
        $this->lead($search, 'Celular com site', ['telefone' => '(47) 99111-2222', 'website' => 'https://exemplo.com.br', 'ai_insights' => ['rating' => 4.8]]);
        $this->lead($search, 'Nota baixa', ['telefone' => '(47) 99333-4444', 'ai_insights' => ['rating' => 3.9]]);
        $this->lead($search, 'Enriquecido sem WhatsApp', ['telefone' => '(47) 99555-6666', 'enrichment_status' => 'done', 'contact_confidence' => 20]);

        $component = Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])->call('abrirBusca', $search->id);

        $component->set('soWhatsApp', true)
            ->assertSee('Celular sem site')->assertSee('Nota baixa')
            ->assertDontSee('Fixo do Google')->assertDontSee('Enriquecido sem WhatsApp');

        $component->set('semSite', true)->assertDontSee('Celular com site');

        $component->set('notaMin', '4.5')->assertSee('Celular sem site')->assertDontSee('Nota baixa');

        $component->set('notaMin', '<script>')->assertSet('notaMin', '');
    }

    public function test_filters_chosen_with_the_search_come_back_with_it(): void
    {
        $search = $this->search(['filtros' => ['so_whatsapp' => false, 'sem_site' => true, 'nota_min' => '4']]);

        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->call('abrirBusca', $search->id)
            ->assertSet('soWhatsApp', false)
            ->assertSet('semSite', true)
            ->assertSet('notaMin', '4');
    }

    public function test_top_selection_skips_leads_that_cannot_be_approached(): void
    {
        $search = $this->search();
        $ok = $this->lead($search, 'Studio Bella', ['lead_score' => 90]);
        $this->lead($search, 'Já abordado', ['lead_score' => 85, 'status' => 'abordado']);
        $this->lead($search, 'Pediu para sair', ['lead_score' => 80, 'opted_out_at' => now()]);
        $this->lead($search, 'Sem telefone', ['lead_score' => 75, 'telefone' => '']);
        $other = $this->lead($search, 'Barbearia Central', ['lead_score' => 70]);

        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->call('abrirBusca', $search->id)
            ->set('soWhatsApp', false)
            ->call('selecionarTop')
            ->assertSet('selecionados', [$ok->id, $other->id]);
    }

    public function test_messages_are_written_for_the_selected_leads_and_the_review_step_opens(): void
    {
        $search = $this->search();
        $ok = $this->lead($search, 'Studio Bella');
        $approached = $this->lead($search, 'Já abordado', ['status' => 'abordado']);
        $optedOut = $this->lead($search, 'Pediu para sair', ['opted_out_at' => now()]);

        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->call('abrirBusca', $search->id)
            ->set('selecionados', [$ok->id, $approached->id, $optedOut->id])
            ->call('gerarAbordagens')
            ->assertSet('passo', 'abordagens')
            ->assertSet('selecionados', [])
            ->assertDispatched('toast', type: 'success', message: trans_choice('messages.bulk_outreach_queued', 1, ['count' => 1]) . ' ' . trans_choice('messages.bulk_outreach_skipped', 2, ['count' => 2]));

        $this->assertSame([$ok->id], OutreachDraft::pluck('lead_id')->all());
        Queue::assertPushed(GenerateOutreachDraftJob::class, 1);
    }

    public function test_too_many_selected_leads_are_refused_before_any_ai_call(): void
    {
        $search = $this->search();
        $ids = collect(range(1, ProspectingWizard::BULK_LIMIT + 1))->map(fn (int $i) => $this->lead($search, "Lead {$i}")->id)->all();

        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->call('abrirBusca', $search->id)
            ->set('selecionados', $ids)
            ->call('gerarAbordagens')
            ->assertDispatched('toast', type: 'error', message: __('messages.bulk_outreach_limit', ['max' => ProspectingWizard::BULK_LIMIT]))
            ->assertSet('selecionados', $ids);

        $this->assertDatabaseCount('outreach_drafts', 0);
    }

    public function test_nothing_to_approach_keeps_the_user_on_the_results(): void
    {
        $search = $this->search();
        $lead = $this->lead($search, 'Telefone ilegível', ['telefone' => '9280-1006']);

        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->call('abrirBusca', $search->id)
            ->set('selecionados', [$lead->id])
            ->call('gerarAbordagens')
            ->assertSet('passo', 'resultados')
            ->assertDispatched('toast', type: 'error', message: __('messages.bulk_outreach_none'));

        $this->assertDatabaseCount('outreach_drafts', 0);
    }

    public function test_saved_search_runs_again_with_the_same_place_and_filters(): void
    {
        $old = $this->search([
            'latitude' => -26.30, 'longitude' => -48.84, 'radius_km' => 4, 'local_label' => 'Joinville - SC, Brasil',
            'filtros' => ['so_whatsapp' => false, 'sem_site' => true, 'nota_min' => null], 'keywords' => ['salão'], 'results_count' => 12,
        ]);

        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->assertSee(__('messages.saved_searches'))
            ->call('buscarDeNovo', $old->id)
            ->assertSet('passo', 'resultados')
            ->assertSet('semSite', true);

        $new = ProspectingSearch::latest('id')->first();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame(['queued', [], 0, 'Joinville - SC, Brasil', 4.0], [$new->status, $new->keywords, (int) $new->results_count, $new->local_label, $new->radius_km]);
        Queue::assertPushed(FindInternetLeadsJob::class, fn (FindInternetLeadsJob $job) => $job->searchId === $new->id && $job->customLat === -26.30);
    }

    public function test_another_empresa_search_cannot_be_opened(): void
    {
        $other = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Outra']);
        $foreign = $this->search(['empresa_id' => $other->id]);
        $this->lead($foreign, 'Lead alheio');

        Livewire::withQueryParams(['busca' => $foreign->id, 'passo' => 'resultados'])
            ->test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->assertSet('searchId', null)
            ->assertSet('passo', 'perfil')
            ->call('abrirBusca', $foreign->id)
            ->assertSet('searchId', null)
            ->set('searchId', $foreign->id)
            ->assertSet('searchId', null)
            ->call('buscarDeNovo', $foreign->id)
            ->assertDontSee('Lead alheio');

        $this->assertDatabaseCount('prospecting_searches', 1);
    }

    public function test_a_search_link_opens_its_results(): void
    {
        $search = $this->search();
        $this->lead($search, 'Studio Bella');

        Livewire::withQueryParams(['busca' => $search->id, 'passo' => 'resultados'])
            ->test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->assertSet('passo', 'resultados')
            ->assertSee('Studio Bella');
    }

    public function test_review_and_pipeline_steps_show_their_components(): void
    {
        Livewire::test(ProspectingWizard::class, ['empresa' => $this->empresa])
            ->call('irPara', 'abordagens')
            ->assertSee(__('messages.outreach_queue_title'))
            ->call('irPara', 'acompanhar')
            ->assertSee(__('messages.pipeline_hint'))
            ->call('irPara', 'resultados')
            ->assertSet('passo', 'acompanhar')
            ->set('passo', 'qualquer')
            ->assertSet('passo', 'perfil');
    }

    public function test_crashed_job_marks_the_search_failed(): void
    {
        $search = $this->search(['status' => 'running']);

        (new FindInternetLeadsJob($search->id))->failed(new RuntimeException('timeout'));

        $this->assertSame('failed', $search->fresh()->status);
        $this->assertSame(__('messages.search_failed_generic'), $search->fresh()->errorMessage());
    }

    public function test_page_requires_login_and_renders_for_the_owner(): void
    {
        $this->get(route('prospeccao.index'))->assertRedirect(route('login'));

        $user = $this->empresa->user;
        $user->forceFill(['onboarding_completed_at' => now()])->save();

        $this->actingAs($user)->get(route('prospeccao.index'))
            ->assertOk()
            ->assertSee(__('messages.prospecting_profile_title'));
    }

    private function search(array $attributes = []): ProspectingSearch
    {
        return ProspectingSearch::create($attributes + [
            'empresa_id'        => $this->empresa->id,
            'descricao_empresa' => 'Sistema de agendamento online para salões e clínicas.',
            'tipo_cliente'      => 'Salões de beleza',
            'latitude'          => -26.9194,
            'longitude'         => -49.0661,
            'radius_km'         => 5,
            'keywords'          => ['salão de beleza'],
            'status'            => 'done',
            'stage'             => 'done',
        ]);
    }

    private function lead(ProspectingSearch $search, string $nome, array $attributes = []): Lead
    {
        return Lead::create($attributes + [
            'empresa_id'            => $search->empresa_id,
            'prospecting_search_id' => $search->id,
            'nome'                  => $nome,
            'telefone'              => '(47) 99280-1006',
            'source'                => 'internet',
            'latitude'              => -26.92,
            'longitude'             => -49.06,
        ]);
    }
}
