<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\ProspectingSearch;
use App\Models\User;
use App\Services\AIService;
use App\Services\Prospecting\ProspectingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ProspectingServiceTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = -26.9;
    private const LNG = -49.0;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_places.key' => 'test-key']);
        $this->empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Embalagens SC', 'cidade' => 'Blumenau']);
    }

    public function test_takes_turns_between_keywords_and_drops_places_outside_radius(): void
    {
        $this->keywords(['padaria', 'confeitaria']);
        $this->fakePlaces([
            'padaria'     => [$this->places('P', 20), $this->places('Q', 5)],
            // C2 sits in the corner of the search rectangle, ~6 km away.
            'confeitaria' => [[$this->place('C1', 0.01), $this->place('C2', 0.04, 0.04)]],
        ]);

        $search = $this->runSearch(radiusKm: 5, maxResults: 6);

        $this->assertSame(['P1', 'C1', 'P2', 'P3', 'P4', 'P5'], Lead::orderBy('id')->pluck('external_id')->all());
        $this->assertSame(['google_places'], Lead::distinct()->pluck('external_source')->all());
        Http::assertSentCount(2);
        $this->assertSame('done', $search->status);
        $this->assertSame(6, $search->results_count);
    }

    public function test_pages_through_results_when_keywords_run_short(): void
    {
        $this->keywords(['padaria']);
        $this->fakePlaces(['padaria' => [$this->places('P', 3), $this->places('Q', 2)]]);

        $this->runSearch(radiusKm: 5);

        $this->assertSame(5, Lead::count());
        Http::assertSent(fn (Request $request) => ($request->data()['pageToken'] ?? null) === 'padaria#1');
        Http::assertSent(function (Request $request) {
            $rectangle = $request->data()['locationRestriction']['rectangle'] ?? null;

            return $rectangle
                && $rectangle['low']['latitude'] < self::LAT && $rectangle['high']['latitude'] > self::LAT
                && $rectangle['low']['longitude'] < self::LNG && $rectangle['high']['longitude'] > self::LNG
                && str_contains($request->header('X-Goog-FieldMask')[0], 'nextPageToken');
        });
    }

    public function test_new_search_keeps_score_and_insights_of_known_lead(): void
    {
        $lead = Lead::create([
            'empresa_id'      => $this->empresa->id,
            'nome'            => 'Padaria Central',
            'telefone'        => '+55 47 3322-1100',
            'source'          => 'internet',
            'external_source' => 'google_places',
            'external_id'     => 'P1',
            'lead_score'      => 87,
            'status'          => 'contatado',
            'ai_insights'     => ['match_score' => 90, 'match_motivo' => 'Compra embalagens toda semana'],
        ]);
        $this->keywords(['padaria']);
        $this->fakePlaces(['padaria' => [[$this->place('P1', 0.01, rating: 4.8)]]]);

        $search = $this->runSearch();

        $lead->refresh();
        $this->assertSame(1, Lead::count());
        $this->assertSame(87, $lead->lead_score);
        $this->assertSame('contatado', $lead->status);
        $this->assertSame($search->id, $lead->prospecting_search_id);
        $this->assertSame(90, $lead->ai_insights['match_score']);
        $this->assertSame(4.8, $lead->ai_insights['rating']);
    }

    private function runSearch(float $radiusKm = 5, int $maxResults = 60): ProspectingSearch
    {
        $search = ProspectingSearch::create([
            'empresa_id'        => $this->empresa->id,
            'descricao_empresa' => 'Fornecedor de embalagens para alimentos',
            'tipo_cliente'      => 'Padarias e confeitarias',
            'latitude'          => self::LAT,
            'longitude'         => self::LNG,
            'radius_km'         => $radiusKm,
            'keywords'          => [],
            'status'            => 'queued',
        ]);

        return app(ProspectingService::class)->run($search, $maxResults, self::LAT, self::LNG, 'Blumenau - SC, Brasil');
    }

    private function keywords(array $keywords): void
    {
        $ai = Mockery::mock(AIService::class);
        $ai->shouldReceive('gerarKeywordsProspeccao')->andReturn($keywords);
        $ai->shouldReceive('buscarLeadsPorPerfil')->andReturn([]);
        $this->app->instance(AIService::class, $ai);
    }

    /** @param array<string, array<int, array>> $pagesByKeyword keyword => its pages of places */
    private function fakePlaces(array $pagesByKeyword): void
    {
        Http::fake(['places.googleapis.com/*' => function (Request $request) use ($pagesByKeyword) {
            $keyword = $request->data()['textQuery'];
            $page = (int) str_replace("{$keyword}#", '', $request->data()['pageToken'] ?? "{$keyword}#0");
            $pages = $pagesByKeyword[$keyword] ?? [];

            return Http::response(array_filter([
                'places'        => $pages[$page] ?? [],
                'nextPageToken' => isset($pages[$page + 1]) ? "{$keyword}#" . ($page + 1) : null,
            ]));
        }]);
    }

    private function places(string $prefix, int $count): array
    {
        return array_map(fn (int $i) => $this->place("{$prefix}{$i}", 0.001 * $i), range(1, $count));
    }

    private function place(string $id, float $latOffset, float $lngOffset = 0, ?float $rating = null): array
    {
        return array_filter([
            'id'          => $id,
            'displayName' => ['text' => "Lugar {$id}"],
            'location'    => ['latitude' => self::LAT + $latOffset, 'longitude' => self::LNG + $lngOffset],
            'rating'      => $rating,
        ]);
    }
}
