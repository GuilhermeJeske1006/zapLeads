<?php

namespace Tests\Feature;

use Anthropic\Client as AnthropicClient;
use App\Models\ApiUsage;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\ProspectingSearch;
use App\Models\User;
use App\Services\AIService;
use App\Services\Costs\UsageMeter;
use App\Services\Enrichment\LeadEnrichmentService;
use App\Services\Enrichment\TwilioLineTypeLookup;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Every paid call is priced and charged to whoever caused it (empresa, search, lead). */
class ApiCostsTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);
    }

    public function test_claude_call_is_priced_with_cache_and_charged_to_the_context(): void
    {
        $this->fakeClaude([$this->answer('claude-sonnet-5-5', ['input_tokens' => 1000, 'output_tokens' => 500, 'cache_read_input_tokens' => 2000, 'cache_creation_input_tokens' => 300])]);

        $text = app(UsageMeter::class)->within(
            ['empresa_id' => $this->empresa->id, 'origem' => 'abordagem'],
            fn () => app(AIService::class)->text('quality', 'Sistema', 'Oi'),
        );

        $this->assertSame('Oi!', $text);
        $usage = ApiUsage::sole();
        $this->assertSame(['anthropic', 'claude-sonnet-5-5', $this->empresa->id, 'abordagem'], [$usage->servico, $usage->sku, $usage->empresa_id, $usage->origem]);
        $this->assertSame([1000, 500, 2000, 300], [$usage->input_tokens, $usage->output_tokens, $usage->cache_read_tokens, $usage->cache_write_tokens]);
        // $2 input, $10 output, $0.20 cache read, $2.50 cache write per million tokens.
        $this->assertEqualsWithDelta((1000 * 2 + 500 * 10 + 2000 * 0.20 + 300 * 2.50) / 1_000_000, $usage->custo_usd, 1e-9);
    }

    public function test_discarded_answer_is_still_billed_and_dated_model_ids_find_their_price(): void
    {
        $this->fakeClaude([$this->answer('claude-haiku-4-5-20251001', ['input_tokens' => 4000, 'output_tokens' => 100], 'max_tokens')]);

        $this->assertNull(app(AIService::class)->structured('fast', 'Sistema', 'Oi', ['type' => 'object'], 100));

        $usage = ApiUsage::sole();
        $this->assertNull($usage->empresa_id);
        $this->assertEqualsWithDelta((4000 * 1 + 100 * 5) / 1_000_000, $usage->custo_usd, 1e-9);
    }

    public function test_web_searches_are_billed_per_search(): void
    {
        $this->fakeClaude([$this->answer('claude-sonnet-5-5', ['input_tokens' => 0, 'output_tokens' => 0, 'server_tool_use' => ['web_search_requests' => 3, 'web_fetch_requests' => 0]])]);

        app(AIService::class)->text('quality', 'Sistema', 'Pesquise');

        $this->assertSame(3, ApiUsage::sole()->web_searches);
        $this->assertEqualsWithDelta(0.03, ApiUsage::sole()->custo_usd, 1e-9);
    }

    public function test_context_nests_and_is_restored(): void
    {
        $meter = app(UsageMeter::class);

        $meter->within(['empresa_id' => $this->empresa->id, 'origem' => 'busca'], function () use ($meter) {
            $meter->within(['lead_id' => null, 'origem' => 'score'], fn () => $meter->lookup());
            $meter->places('text_search_enterprise');
        });
        $meter->lookup();

        $this->assertSame(
            [[$this->empresa->id, 'score', 0.008], [$this->empresa->id, 'busca', 0.035], [null, null, 0.008]],
            ApiUsage::orderBy('id')->get()->map(fn ($u) => [$u->empresa_id, $u->origem, round($u->custo_usd, 6)])->all(),
        );
    }

    public function test_enrichment_from_a_search_batch_is_charged_to_the_search(): void
    {
        config(['services.google_places.key' => 'test-key']);
        Http::fake(['places.googleapis.com/*' => Http::response(['businessStatus' => 'OPERATIONAL'])]);
        $search = $this->search();
        $lead = Lead::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Studio Bella', 'telefone' => '', 'source' => 'internet',
            'external_source' => 'google_places', 'external_id' => 'ChIJstudio', 'prospecting_search_id' => $search->id,
        ]);

        app(LeadEnrichmentService::class)->enrich($lead, $search->id);
        app(LeadEnrichmentService::class)->enrich($lead); // "Buscar contatos": not the search's

        $this->assertSame(
            [['place_details_enterprise_atmosphere', $search->id, $lead->id, 'enriquecimento'], ['place_details_enterprise_atmosphere', null, $lead->id, 'enriquecimento']],
            ApiUsage::orderBy('id')->get()->map(fn ($u) => [$u->sku, $u->prospecting_search_id, $u->lead_id, $u->origem])->all(),
        );
    }

    public function test_failed_places_request_is_not_billed(): void
    {
        config(['services.google_places.key' => 'test-key']);
        Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429)]);
        $lead = Lead::create(['empresa_id' => $this->empresa->id, 'nome' => 'X', 'telefone' => '', 'external_source' => 'google_places', 'external_id' => 'ChIJx']);

        app(LeadEnrichmentService::class)->enrich($lead);

        $this->assertDatabaseCount('api_usages', 0);
    }

    public function test_twilio_lookup_is_billed_when_twilio_answers_and_not_again_from_cache(): void
    {
        $lookup = new class extends TwilioLineTypeLookup {
            protected function request(string $e164): array
            {
                return $e164 === '+14155550100' ? ['type' => 'mobile'] : throw new \RuntimeException('20404 not found');
            }
        };

        $this->assertSame('mobile', $lookup->lineType('+14155550100'));
        $this->assertSame('mobile', $lookup->lineType('+14155550100'));
        $this->assertNull($lookup->lineType('+14155550199'));

        $this->assertSame(['twilio_lookup', 'line_type_intelligence', 0.008], [ApiUsage::sole()->servico, ApiUsage::sole()->sku, ApiUsage::sole()->custo_usd]);
    }

    public function test_finished_search_keeps_a_summary_of_what_it_cost(): void
    {
        $search = $this->search();
        $meter = app(UsageMeter::class);
        $meter->within(['prospecting_search_id' => $search->id], function () use ($meter) {
            $meter->places('text_search_enterprise');
            $meter->places('text_search_enterprise');
            $meter->claude('claude-haiku-4-5-20251001', 10_000, 2_000);
        });

        $search->advance('done');

        $custos = $search->fresh()->custos;
        $this->assertEqualsWithDelta(0.07 + 0.02, $custos['total_usd'], 1e-9);
        $this->assertSame([['google_places', 'text_search_enterprise', 2], ['anthropic', 'claude-haiku-4-5-20251001', 1]], array_map(fn ($i) => [$i['servico'], $i['sku'], $i['chamadas']], $custos['itens']));
        $this->assertSame(10_000, $custos['itens'][1]['input_tokens']);
    }

    public function test_admin_sees_costs_and_cost_per_qualified_lead(): void
    {
        $search = $this->search();
        foreach ([[90, '+55 47 99999-0001'], [80, '+55 47 99999-0002'], [90, '+55 47 3322-1100'], [40, '+55 47 99999-0003']] as [$fit, $phone]) {
            Lead::create([
                'empresa_id' => $this->empresa->id, 'nome' => "Lead {$fit}", 'telefone' => $phone, 'source' => 'internet',
                'prospecting_search_id' => $search->id, 'ai_insights' => ['match_score' => $fit],
            ]);
        }
        $meter = app(UsageMeter::class);
        $meter->within(['empresa_id' => $this->empresa->id, 'prospecting_search_id' => $search->id, 'origem' => 'busca'], fn () => $meter->claude('claude-sonnet-5-5', 0, 100_000)); // $1.00
        $meter->within(['empresa_id' => $this->empresa->id, 'origem' => 'abordagem'], fn () => $meter->claude('claude-sonnet-5-5', 0, 50_000)); // $0.50
        $search->advance('done');

        $admin = User::factory()->create(['is_master_admin' => true]);
        $response = $this->actingAs($admin)->get(route('admin.costs.index', ['dias' => 7]));

        $response->assertOk();
        // 2 qualified leads (fit ≥ 70 and a mobile): $1.00 to find them, $0.50 each.
        $response->assertSeeInOrder(['Agenda Fácil', '1', 'US$ 1,00', 'US$ 1,50', '2', 'US$ 0,5000'], false);
        $response->assertSee('claude-sonnet-5-5');

        $this->actingAs($this->empresa->user)->get(route('admin.costs.index'))->assertForbidden();
    }

    private function search(): ProspectingSearch
    {
        return ProspectingSearch::create([
            'empresa_id' => $this->empresa->id, 'descricao_empresa' => 'Agenda online', 'tipo_cliente' => 'Salões',
            'latitude' => -26.9, 'longitude' => -49.0, 'radius_km' => 5, 'keywords' => [], 'status' => 'done',
        ]);
    }

    private function fakeClaude(array $responses): void
    {
        $this->app->instance(AnthropicClient::class, new AnthropicClient(
            apiKey: 'test-key',
            requestOptions: ['transporter' => new GuzzleClient(['handler' => HandlerStack::create(new MockHandler($responses))]), 'maxRetries' => 0],
        ));
    }

    private function answer(string $model, array $usage, string $stopReason = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_' . uniqid(), 'type' => 'message', 'role' => 'assistant', 'model' => $model,
            'content' => [['type' => 'text', 'text' => 'Oi!']],
            'stop_reason' => $stopReason, 'stop_sequence' => null,
            'usage' => $usage,
        ]));
    }
}
