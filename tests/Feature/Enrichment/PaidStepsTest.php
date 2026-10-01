<?php

namespace Tests\Feature\Enrichment;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\User;
use App\Services\AIService;
use App\Services\Enrichment\LeadEnrichmentService;
use App\Services\Enrichment\TwilioLineTypeLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

/** Web research and Twilio Lookup cost money per call: they only run when enabled, for good leads that need them. */
class PaidStepsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_places.key'       => '',
            'services.enrichment.web_research' => true,
            'twilio.lookup_enabled'            => true,
        ]);
        Http::preventStrayRequests();
    }

    public function test_research_keeps_only_facts_with_a_source_the_search_returned(): void
    {
        $this->mock(TwilioLineTypeLookup::class, fn (MockInterface $m) => $m->shouldNotReceive('lineType'));
        $this->mock(AIService::class, function (MockInterface $ai) {
            $ai->shouldReceive('pesquisarContatosNaWeb')->once()->with('Studio Bella', 'Blumenau', null)->andReturn([
                'texto' => 'O WhatsApp do Studio Bella é (47) 99999-8888, segundo o Instagram oficial. Dona: Carla Souza.',
                'urls'  => ['https://www.instagram.com/studiobella.blu/', 'https://guiablumenau.com.br/studio-bella'],
            ]);
            $ai->shouldReceive('extrairContatosDaPesquisa')->once()->andReturn([
                ['tipo' => 'whatsapp', 'valor' => '(47) 99999-8888', 'evidencia_url' => 'https://www.instagram.com/studiobella.blu'],
                ['tipo' => 'whatsapp', 'valor' => '(47) 97777-6666', 'evidencia_url' => 'https://site-inventado.com'],
                ['tipo' => 'instagram', 'valor' => 'https://www.instagram.com/studiobella.blu/', 'evidencia_url' => 'https://www.instagram.com/studiobella.blu/'],
                ['tipo' => 'proprietario', 'valor' => 'Carla Souza', 'evidencia_url' => 'https://guiablumenau.com.br/studio-bella'],
            ]);
        });
        $lead = $this->lead(fit: 85);

        app(LeadEnrichmentService::class)->enrich($lead);

        $lead->refresh();
        $whatsapp = $lead->contacts()->where('valor_e164', '+5547999998888')->sole();
        $this->assertSame(['whatsapp', 'web_research', 55, 'https://www.instagram.com/studiobella.blu'], [$whatsapp->tipo, $whatsapp->origem, $whatsapp->confianca, $whatsapp->evidencia]);
        $this->assertFalse($lead->contacts()->where('valor_e164', '+5547977776666')->exists());
        $this->assertSame('@studiobella.blu', $lead->instagram);
        $this->assertSame(['Carla Souza', 'Proprietário (fonte: web)'], [$lead->decisor_nome, $lead->decisor_cargo]);
        $this->assertCount(2, $lead->dossie['pesquisa_web']['fontes']);
    }

    public function test_research_is_skipped_when_disabled_for_weak_leads_or_with_a_reliable_whatsapp(): void
    {
        $this->mock(AIService::class, fn (MockInterface $ai) => $ai->shouldNotReceive('pesquisarContatosNaWeb'));
        $this->mock(TwilioLineTypeLookup::class, fn (MockInterface $m) => $m->shouldNotReceive('lineType'));

        app(LeadEnrichmentService::class)->enrich($this->lead(fit: 40));
        app(LeadEnrichmentService::class)->enrich($this->lead(fit: 90, telefone: '+55 47 99999-8888')); // Google mobile: 70

        config(['services.enrichment.web_research' => false]);
        app(LeadEnrichmentService::class)->enrich($this->lead(fit: 90));
    }

    public function test_lookup_only_asks_about_numbers_the_numbering_plan_cannot_classify(): void
    {
        config(['services.enrichment.web_research' => false]);
        $this->mock(TwilioLineTypeLookup::class, fn (MockInterface $m) => $m
            ->shouldReceive('lineType')->once()->with('+14155238886')->andReturn('mobile'));
        $lead = $this->lead(fit: 90, telefone: '+1 415 523 8886');
        $lead->update(['website' => null]);

        app(LeadEnrichmentService::class)->enrich($lead);
        app(LeadEnrichmentService::class)->enrich($this->lead(fit: 90, telefone: '+55 47 3322-1100'));

        $contact = $lead->contacts()->sole();
        $this->assertSame('mobile', $contact->line_type);
        $this->assertNotNull($contact->verificado_em);
        $this->assertTrue($contact->is_primary);
    }

    private function lead(int $fit, string $telefone = ''): Lead
    {
        $empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);

        return Lead::create([
            'empresa_id'  => $empresa->id,
            'nome'        => 'Studio Bella',
            'cidade'      => 'Blumenau',
            'telefone'    => $telefone,
            'ai_insights' => ['match_score' => $fit],
        ]);
    }
}
