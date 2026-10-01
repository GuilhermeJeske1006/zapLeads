<?php

namespace Tests\Feature\Enrichment;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\User;
use App\Services\Enrichment\LeadEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GooglePlaceDetailsStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_places.key' => 'test-key']);
        Http::preventStrayRequests();
    }

    public function test_keeps_hours_reviews_and_phone_but_not_who_wrote_the_reviews(): void
    {
        Http::fake(['places.googleapis.com/v1/places/*' => Http::response([
            'internationalPhoneNumber' => '+55 47 99999-8888',
            'businessStatus'           => 'OPERATIONAL',
            'rating'                   => 4.8,
            'userRatingCount'          => 212,
            'primaryTypeDisplayName'   => ['text' => 'Salão de beleza'],
            'regularOpeningHours'      => [
                'openNow'             => true,
                'periods'             => [['open' => ['day' => 2, 'hour' => 9, 'minute' => 0], 'close' => ['day' => 2, 'hour' => 19, 'minute' => 0]]],
                'weekdayDescriptions' => ['terça-feira: 09:00–19:00'],
            ],
            'reviews' => array_map(fn (int $i) => [
                'rating'                         => 3,
                'text'                           => ['text' => "Demorei pra conseguir horário pelo WhatsApp ({$i})"],
                'relativePublishTimeDescription' => 'há 2 semanas',
                'authorAttribution'              => ['displayName' => 'Cliente Real'],
            ], range(1, 7)),
        ])]);
        $lead = $this->lead();

        app(LeadEnrichmentService::class)->enrich($lead);

        $lead->refresh();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'places/ChIJstudio') && str_contains($r->header('X-Goog-FieldMask')[0], 'reviews'));
        $this->assertCount(5, $lead->dossie['reviews']);
        $this->assertSame(['nota' => 3, 'texto' => 'Demorei pra conseguir horário pelo WhatsApp (1)', 'quando' => 'há 2 semanas'], $lead->dossie['reviews'][0]);
        $this->assertStringNotContainsString('Cliente Real', json_encode($lead->dossie));
        $this->assertSame('Salão de beleza', $lead->dossie['segmento']);
        $this->assertSame(['terça-feira: 09:00–19:00'], $lead->horario_funcionamento['weekdayDescriptions']);
        $this->assertSame(212, $lead->ai_insights['user_ratings_total']);
        $this->assertSame(70, $lead->contacts()->sole()->confianca);
    }

    public function test_permanently_closed_place_is_discarded(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response(['businessStatus' => 'CLOSED_PERMANENTLY'])]);
        $lead = $this->lead();

        app(LeadEnrichmentService::class)->enrich($lead);

        $lead->refresh();
        $this->assertSame(['descartado', 'closed_permanently', 'CLOSED_PERMANENTLY'], [$lead->status, $lead->dossie['descartado'], $lead->business_status]);
        Http::assertSentCount(1); // nothing else is paid for
    }

    private function lead(): Lead
    {
        $empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);

        return Lead::create([
            'empresa_id' => $empresa->id, 'nome' => 'Studio Bella', 'telefone' => '+55 47 99999-8888',
            'source' => 'internet', 'external_source' => 'google_places', 'external_id' => 'ChIJstudio',
        ]);
    }
}
