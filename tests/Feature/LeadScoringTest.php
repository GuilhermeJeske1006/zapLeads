<?php

namespace Tests\Feature;

use App\Jobs\ScoreLeadsJob;
use App\Livewire\Leads\LeadsTable;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\ProspectingSearch;
use App\Models\User;
use App\Services\AIService;
use App\Services\Enrichment\LeadEnrichmentService;
use App\Services\Scoring\LeadScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/** lead_score = 40% fit + 25% contactability + 20% pain + 15% proximity. */
class LeadScoringTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'user_id'           => User::factory()->create()->id,
            'nome'              => 'Agenda Fácil',
            'descricao_empresa' => 'Agenda online para salões',
            'tipo_cliente_alvo' => 'Salões de beleza',
            'oferta_principal'  => 'Agenda com confirmação pelo WhatsApp',
            'raio_atendimento'  => 10,
        ]);
    }

    public function test_score_weighs_fit_contact_pain_and_proximity(): void
    {
        $lead = $this->lead([
            'contact_confidence' => 90,
            'distancia_km'       => 1,
            'prospecting_search_id' => $this->search(radiusKm: 5)->id,
            'ai_insights'        => ['match_score' => 80, 'dor_score' => 60],
        ]);

        app(LeadScoringService::class)->score($lead);

        // 0.40*80 + 0.25*90 + 0.20*60 + 0.15*80 = 78.5
        $this->assertSame(79, $lead->lead_score);
        $this->assertSame(
            ['fit' => 80, 'contatabilidade' => 90, 'dor' => 60, 'proximidade' => 80, 'contatabilidade_estimada' => false],
            $lead->ai_insights['score_breakdown'],
        );
        $this->assertSame('warm', $lead->score_label);
    }

    public function test_before_enrichment_contact_is_estimated_from_the_phone(): void
    {
        $scoring = app(LeadScoringService::class);
        $breakdown = function (string $telefone) use ($scoring) {
            $lead = $this->lead(['telefone' => $telefone, 'external_source' => 'google_places']);
            $scoring->score($lead);

            return [$lead->ai_insights['score_breakdown']['contatabilidade'], $lead->ai_insights['score_breakdown']['contatabilidade_estimada']];
        };

        $this->assertSame([70, true], $breakdown('+55 47 99999-8888'));
        $this->assertSame([20, true], $breakdown('+55 47 3322-1100'));
        $this->assertSame([0, true], $breakdown(''));
    }

    public function test_proximity_goes_from_the_center_to_the_edge_of_the_radius(): void
    {
        $search = $this->search(radiusKm: 4);
        $proximity = function (array $attributes) {
            $lead = $this->lead($attributes);
            app(LeadScoringService::class)->score($lead);

            return $lead->ai_insights['score_breakdown']['proximidade'];
        };

        $this->assertSame(100, $proximity(['distancia_km' => 0, 'prospecting_search_id' => $search->id]));
        $this->assertSame(50, $proximity(['distancia_km' => 2, 'prospecting_search_id' => $search->id]));
        $this->assertSame(0, $proximity(['distancia_km' => 6, 'prospecting_search_id' => $search->id]));
        // Without a search, the empresa's service radius (10 km).
        $this->assertSame(80, $proximity(['distancia_km' => 2]));
        $this->assertNull($proximity(['distancia_km' => null]));
    }

    public function test_evaluation_stores_fit_pain_and_hook_and_sends_no_contact_data(): void
    {
        $lead = $this->lead([
            'telefone'     => '+55 47 99999-8888',
            'email'        => 'contato@studiobella.com.br',
            'decisor_nome' => 'Carla Souza',
            'website'      => 'https://studiobella.com.br',
            'dossie'       => ['segmento' => 'Salão de beleza', 'reviews' => [['nota' => 2, 'texto' => 'Nunca respondem o WhatsApp', 'quando' => 'há 1 mês']]],
        ]);
        $this->mock(AIService::class, function (MockInterface $ai) use ($lead) {
            $ai->shouldReceive('avaliarLeads')->once()->withArgs(function (array $empresa, array $leads) {
                $sent = json_encode($leads);

                return $empresa['oferta_principal'] === 'Agenda com confirmação pelo WhatsApp'
                    && $leads[0]['segmento'] === 'Salão de beleza'
                    && $leads[0]['avaliacoes_recentes'][0]['texto'] === 'Nunca respondem o WhatsApp'
                    && $leads[0]['tem_site'] === true
                    && !str_contains($sent, '99999') && !str_contains($sent, 'contato@') && !str_contains($sent, 'Carla');
            })->andReturn([[
                'id' => $lead->id, 'fit' => 85, 'motivo' => 'Salão com agenda pelo WhatsApp', 'dor' => 70,
                'dor_provavel' => 'Clientes sem resposta no WhatsApp', 'gancho' => 'Avaliação recente diz que nunca respondem o WhatsApp',
            ]]);
        });

        app(LeadScoringService::class)->evaluate($this->empresa, [$lead]);

        $lead->refresh();
        $this->assertSame(85, $lead->fitScore());
        $this->assertSame(70, $lead->ai_insights['dor_score']);
        $this->assertSame('Avaliação recente diz que nunca respondem o WhatsApp', $lead->ai_insights['gancho']);
        // 0.40*85 + 0.25*70 (Google mobile, estimated) + 0.20*70 = 65.5
        $this->assertSame(66, $lead->lead_score);
    }

    public function test_a_failed_evaluation_keeps_the_previous_fit(): void
    {
        $lead = $this->lead(['ai_insights' => ['match_score' => 60, 'match_motivo' => 'Salão', 'dor_score' => 40]]);
        $this->mock(AIService::class, fn (MockInterface $ai) => $ai->shouldReceive('avaliarLeads')->andReturn([]));

        app(LeadScoringService::class)->evaluate($this->empresa, [$lead]);

        $lead->refresh();
        $this->assertSame([60, 'Salão'], [$lead->ai_insights['match_score'], $lead->ai_insights['match_motivo']]);
        $this->assertSame(32, $lead->lead_score); // 0.40*60 + 0.20*40
    }

    public function test_enrichment_rescores_the_lead_with_its_real_contact_confidence(): void
    {
        config(['services.google_places.key' => '', 'services.enrichment.web_research' => false, 'twilio.lookup_enabled' => false]);
        Http::preventStrayRequests();
        $lead = $this->lead(['telefone' => '+55 47 99999-8888', 'external_source' => 'google_places', 'ai_insights' => ['match_score' => 50]]);
        $this->mock(AIService::class, fn (MockInterface $ai) => $ai->shouldReceive('avaliarLeads')->once()->andReturn([
            ['id' => $lead->id, 'fit' => 80, 'motivo' => 'ok', 'dor' => 50, 'dor_provavel' => null, 'gancho' => null],
        ]));

        app(LeadEnrichmentService::class)->enrich($lead);

        $lead->refresh();
        $this->assertSame('done', $lead->enrichment_status);
        $this->assertSame(70, $lead->contact_confidence);
        $this->assertFalse($lead->ai_insights['score_breakdown']['contatabilidade_estimada']);
        $this->assertSame(60, $lead->lead_score); // 0.40*80 + 0.25*70 + 0.20*50 = 59.5
    }

    public function test_rescore_queues_prospect_leads_still_in_play_once_per_ten_minutes(): void
    {
        Queue::fake();
        $prospects = collect(range(1, 16))->map(fn (int $i) => $this->lead(['nome' => "Salão {$i}"]));
        $this->lead(['source' => 'internal']);                    // catalog lead: keeps its distance score
        $this->lead(['status' => 'descartado']);
        $this->lead(['opted_out_at' => now()]);
        $other = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Outra']);
        Lead::create(['empresa_id' => $other->id, 'nome' => 'De outra empresa', 'telefone' => '', 'source' => 'internet']);

        $scoring = app(LeadScoringService::class);

        $this->assertSame(16, $scoring->queueRescore($this->empresa));
        $this->assertNull($scoring->queueRescore($this->empresa));

        $queued = [];
        Queue::assertPushed(ScoreLeadsJob::class, function (ScoreLeadsJob $job) use (&$queued) {
            $queued = [...$queued, ...$job->leadIds];

            return $job->empresaId === $this->empresa->id && count($job->leadIds) <= 15;
        });
        Queue::assertPushed(ScoreLeadsJob::class, 2);
        $this->assertEqualsCanonicalizing($prospects->pluck('id')->all(), $queued);
    }

    public function test_score_job_only_touches_leads_of_its_empresa(): void
    {
        $mine = $this->lead();
        $other = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Outra']);
        $theirs = Lead::create(['empresa_id' => $other->id, 'nome' => 'Deles', 'telefone' => '', 'source' => 'internet', 'lead_score' => 50]);
        $this->mock(AIService::class, fn (MockInterface $ai) => $ai->shouldReceive('avaliarLeads')->once()
            ->withArgs(fn (array $empresa, array $leads) => array_column($leads, 'id') === [$mine->id])
            ->andReturn([]));

        (new ScoreLeadsJob($this->empresa->id, [$mine->id, $theirs->id]))->handle(app(LeadScoringService::class));

        $this->assertSame(50, $theirs->fresh()->lead_score);
        $this->assertArrayHasKey('score_breakdown', $mine->fresh()->ai_insights);
    }

    public function test_command_replaces_the_fixed_50_of_prospect_leads_only(): void
    {
        $prospect = $this->lead(['lead_score' => 50, 'ai_insights' => ['match_score' => 90]]);
        $catalog = $this->lead(['lead_score' => 50, 'source' => 'internal']);

        $this->artisan('leads:score')->assertSuccessful();

        $this->assertSame(36, $prospect->fresh()->lead_score);
        $this->assertSame(50, $catalog->fresh()->lead_score);
    }

    public function test_leads_table_button_queues_the_rescore(): void
    {
        Queue::fake();
        $this->lead();

        Livewire::test(LeadsTable::class, ['empresa' => $this->empresa])
            ->call('recalcularScores')
            ->assertDispatched('toast', type: 'success', message: __('messages.rescore_queued', ['count' => 1]))
            ->call('recalcularScores')
            ->assertDispatched('toast', type: 'info', message: __('messages.rescore_running'));

        Queue::assertPushed(ScoreLeadsJob::class, 1);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::create($attributes + [
            'empresa_id' => $this->empresa->id,
            'nome'       => 'Studio Bella',
            'telefone'   => '',
            'source'     => 'internet',
        ]);
    }

    private function search(float $radiusKm): ProspectingSearch
    {
        return ProspectingSearch::create([
            'empresa_id' => $this->empresa->id, 'descricao_empresa' => 'Agenda', 'tipo_cliente' => 'Salões',
            'latitude' => -26.9, 'longitude' => -49.0, 'radius_km' => $radiusKm, 'keywords' => [], 'status' => 'done',
        ]);
    }
}
