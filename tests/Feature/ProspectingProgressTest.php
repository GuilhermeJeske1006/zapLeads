<?php

namespace Tests\Feature;

use App\Events\ProspectingSearchUpdated;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\ProspectingSearch;
use App\Models\User;
use App\Services\AIService;
use App\Services\Enrichment\LeadEnrichmentService;
use App\Services\Prospecting\ProspectingService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ProspectingProgressTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_places.key' => 'test-key']);
        $this->empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Embalagens SC', 'cidade' => 'Blumenau']);
    }

    public function test_search_reports_each_stage_with_its_counts(): void
    {
        Bus::fake();
        $stages = [];
        Event::listen(ProspectingSearchUpdated::class, function (ProspectingSearchUpdated $event) use (&$stages) {
            $stages[] = "{$event->search->status}:{$event->search->stage}";
        });
        $this->fakeSearch(['padaria', 'confeitaria'], places: 3);

        $search = $this->runSearch();

        $this->assertSame(['running:keywords', 'running:searching', 'running:ranking', 'running:enriching', 'done:enriching'], $stages);
        $this->assertSame('done', $search->status);
        $this->assertSame(['keywords' => 2, 'empresas' => 3, 'enriquecer' => 3], array_diff_key($search->progress, ['batch_id' => true]));
        $this->assertArrayHasKey('batch_id', $search->progress);
        $this->assertTrue($search->isActive(), 'still looking up contacts');
    }

    public function test_search_without_leads_to_enrich_is_done_at_once(): void
    {
        Bus::fake();
        $this->fakeSearch(['padaria'], places: 0);

        $search = $this->runSearch();

        $this->assertSame(['done', 'done'], [$search->status, $search->stage]);
        $this->assertFalse($search->isActive());
        Bus::assertNothingBatched();
    }

    public function test_enrichment_counts_each_lead_and_finishes_the_search(): void
    {
        $search = $this->search(['status' => 'done', 'stage' => 'ranking']);
        foreach (['A', 'B'] as $nome) {
            Lead::create(['empresa_id' => $this->empresa->id, 'prospecting_search_id' => $search->id, 'nome' => $nome, 'telefone' => '', 'source' => 'internet']);
        }
        $this->partialMock(LeadEnrichmentService::class, fn ($mock) => $mock->shouldReceive('enrich')
            ->andReturnUsing(fn (Lead $lead) => $lead->update(['enrichment_status' => 'done'])));

        $seen = [];
        Event::listen(ProspectingSearchUpdated::class, function (ProspectingSearchUpdated $event) use (&$seen) {
            $search = $event->search->fresh();
            $seen[] = $search->stage === 'enriching' ? $search->enrichmentProgress() : $search->stage;
        });

        // Sync queue: the batch runs inside queueSearch().
        app(LeadEnrichmentService::class)->queueSearch($search);

        $this->assertSame([
            ['done' => 0, 'total' => 2],
            ['done' => 1, 'total' => 2],
            ['done' => 2, 'total' => 2],
            'done',
        ], $seen);
        $this->assertSame('done', $search->fresh()->stage);
        // Read from the batch itself once its id is saved.
        $this->assertNotNull($search->fresh()->progress['batch_id'] ?? null);
        Lead::query()->update(['enrichment_status' => 'pending']);
        $this->assertSame(['done' => 2, 'total' => 2], $search->fresh()->enrichmentProgress());
    }

    public function test_a_broadcaster_that_is_down_does_not_fail_the_search(): void
    {
        Bus::fake();
        Event::listen(ProspectingSearchUpdated::class, fn () => throw new BroadcastException('Reverb is down'));
        $this->fakeSearch(['padaria'], places: 2);

        $search = $this->runSearch();

        $this->assertSame('done', $search->status);
        $this->assertSame(2, $search->results_count);
    }

    public function test_failed_search_stores_a_message_the_user_can_act_on(): void
    {
        config(['services.google_places.key' => null, 'services.mapbox.token' => null]);

        $search = $this->runSearch();

        $this->assertSame('failed', $search->status);
        $this->assertSame(__('messages.search_error_no_provider'), $search->errorMessage());
    }

    public function test_progress_is_broadcast_only_to_the_empresa(): void
    {
        $event = new ProspectingSearchUpdated($this->search());
        $this->assertSame("private-empresa.{$this->empresa->id}.prospecting", $event->broadcastOn()[0]->name);
        $this->assertSame(['id', 'status', 'stage'], array_keys($event->broadcastWith()));

        $authorize = app(BroadcastManager::class)->driver()->getChannels()->get('empresa.{empresaId}.prospecting');
        $owner = $this->empresa->user;
        $stranger = User::factory()->create();
        Empresa::create(['user_id' => $stranger->id, 'nome' => 'Outra']);

        $this->assertTrue($authorize($owner, $this->empresa->id));
        $this->assertFalse($authorize($stranger->fresh(), $this->empresa->id));
    }

    private function runSearch(): ProspectingSearch
    {
        return app(ProspectingService::class)->run($this->search(), 60, -26.9, -49.0, 'Blumenau - SC, Brasil');
    }

    private function search(array $attributes = []): ProspectingSearch
    {
        return ProspectingSearch::create($attributes + [
            'empresa_id'        => $this->empresa->id,
            'descricao_empresa' => 'Fornecedor de embalagens para alimentos',
            'tipo_cliente'      => 'Padarias',
            'latitude'          => -26.9,
            'longitude'         => -49.0,
            'radius_km'         => 5,
            'keywords'          => [],
            'status'            => 'queued',
        ]);
    }

    private function fakeSearch(array $keywords, int $places): void
    {
        $ai = Mockery::mock(AIService::class);
        $ai->shouldReceive('gerarKeywordsProspeccao')->andReturn($keywords);
        $ai->shouldReceive('avaliarLeads')->andReturn([]);
        $this->app->instance(AIService::class, $ai);

        $first = $keywords[0];
        Http::fake(['places.googleapis.com/*' => fn ($request) => Http::response([
            'places' => $request->data()['textQuery'] === $first ? array_map(fn (int $i) => [
                'id'          => "P{$i}",
                'displayName' => ['text' => "Padaria {$i}"],
                'location'    => ['latitude' => -26.9 + 0.001 * $i, 'longitude' => -49.0],
            ], $places ? range(1, $places) : []) : [],
        ])]);
    }
}
