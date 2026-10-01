<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\ProspectingMetrics;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachAttempt;
use App\Models\ProspectingSearch;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\Metrics\FunnelReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FunnelReportTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private ProspectingSearch $search;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->empresa('Agenda Fácil');
        $this->search = $this->search($this->empresa);
    }

    public function test_each_lead_counts_in_every_stage_it_reached(): void
    {
        $this->lead('+55 47 99999-0001');                                    // found, mobile
        $this->lead('+55 47 3322-1100');                                     // landline, not enriched
        $this->lead('+55 47 3322-1101', enriched: 90);                       // landline with WhatsApp on the site
        $this->lead('+55 47 99999-0002', enriched: 20);                      // mobile, enrichment found no WhatsApp
        $this->lead('+55 47 99999-0003', status: 'abordado', attempt: true);
        $this->lead('+55 47 99999-0004', status: 'descartado', attempt: true, replied: true); // dropped after replying
        $this->lead('+55 47 99999-0005', status: 'reuniao');                 // moved by hand
        $this->lead('+55 47 99999-0006', status: 'convertido', attempt: true, replied: true);
        // Not found by a search: catalog and hand-typed leads.
        $this->lead('+55 47 99999-0007', source: 'internal', status: 'convertido');
        $this->lead('+55 47 99999-0008', source: 'manual');

        $this->assertSame(
            ['buscados' => 8, 'whatsapp' => 6, 'abordados' => 4, 'responderam' => 3, 'reuniao' => 2, 'convertidos' => 1],
            app(FunnelReport::class)->funnel($this->empresa),
        );
    }

    public function test_period_or_search_picks_the_cohort(): void
    {
        $other = $this->search($this->empresa);
        $this->lead('+55 47 99999-0001');
        $this->lead('+55 47 99999-0002', search: $other);
        $old = $this->lead('+55 47 99999-0003');
        Lead::whereKey($old->id)->update(['created_at' => now()->subDays(40)]);
        $this->lead('+55 47 99999-0004', empresa: $this->empresa('Outra'));

        $report = app(FunnelReport::class);
        $this->assertSame(2, $report->funnel($this->empresa, 30)['buscados']);
        $this->assertSame(3, $report->funnel($this->empresa)['buscados']);
        // A search is its own cohort: the period doesn't apply.
        $this->assertSame(2, $report->funnel($this->empresa, 30, $this->search->id)['buscados']);
        $this->assertSame(1, $report->funnel($this->empresa, 30, $other->id)['buscados']);
    }

    public function test_reply_rates_by_template_and_by_how_it_was_sent(): void
    {
        $lead = $this->lead('+55 47 99999-0001');
        $template = WhatsAppTemplate::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'com_nome', 'content_sid' => 'HX' . str_repeat('a', 32),
            'corpo_preview' => 'Olá, {{1}}! {{2}}', 'variaveis' => ['1' => 'contato_nome', '2' => 'mensagem_sem_saudacao'], 'status' => 'approved',
        ]);
        $this->attempt($lead, 'api', template: $template, replied: true);
        $this->attempt($lead, 'api', template: $template);
        $this->attempt($lead, 'api');
        $this->attempt($lead, 'assisted', replied: true);
        $this->attempt($lead, 'assisted', etapa: 1); // follow-ups don't count for the first message

        $report = app(FunnelReport::class);
        $this->assertSame([['template' => 'com_nome', 'envios' => 2, 'respostas' => 1, 'taxa' => 0.5]], $report->byTemplate($this->empresa->id));
        $this->assertSame([
            ['canal' => 'api', 'envios' => 3, 'respostas' => 1, 'taxa' => 1 / 3],
            ['canal' => 'assisted', 'envios' => 1, 'respostas' => 1, 'taxa' => 1.0],
        ], $report->byChannel($this->empresa->id));
    }

    public function test_dashboard_shows_the_funnel_and_ignores_searches_of_other_empresas(): void
    {
        $this->lead('+55 47 99999-0001', status: 'abordado', attempt: true);
        $foreign = $this->search($this->empresa('Outra'));
        $this->lead('+55 47 99999-0002', search: $foreign, empresa: $foreign->empresa);

        $this->actingAs($this->empresa->user);
        Livewire::test(ProspectingMetrics::class, ['empresa' => $this->empresa])
            ->assertSeeInOrder([__('messages.funnel_stage_buscados'), '1', __('messages.funnel_stage_abordados'), '1'])
            ->assertSee(__('messages.ab_collecting', ['sent' => 1, 'min' => 30]))
            ->set('buscaId', (string) $foreign->id)
            ->assertViewHas('funil', fn (array $funil) => $funil['buscados'] === 1)
            ->set('periodo', '9999')
            ->assertSet('periodo', '30');
    }

    private function lead(
        string $telefone,
        string $status = 'novo',
        bool $attempt = false,
        bool $replied = false,
        ?int $enriched = null,
        string $source = 'internet',
        ?ProspectingSearch $search = null,
        ?Empresa $empresa = null,
    ): Lead {
        $empresa ??= $this->empresa;
        $lead = Lead::create([
            'empresa_id'            => $empresa->id,
            'nome'                  => "Lead {$telefone}",
            'telefone'              => $telefone,
            'source'                => $source,
            'status'                => $status,
            'prospecting_search_id' => ($search ?? $this->search)->id,
            'enrichment_status'     => $enriched !== null ? 'done' : null,
            'contact_confidence'    => $enriched,
        ]);

        if ($attempt) {
            $this->attempt($lead, 'assisted', replied: $replied);
        }

        return $lead;
    }

    private function attempt(Lead $lead, string $canal, ?WhatsAppTemplate $template = null, bool $replied = false, int $etapa = 0): void
    {
        OutreachAttempt::create([
            'empresa_id'           => $lead->empresa_id,
            'lead_id'              => $lead->id,
            'canal'                => $canal,
            'whatsapp_template_id' => $template?->id,
            'mensagem'             => 'Oi?',
            'variante'             => 'observacao',
            'etapa'                => $etapa,
            'responded_at'         => $replied ? now() : null,
        ]);
    }

    private function search(Empresa $empresa): ProspectingSearch
    {
        return ProspectingSearch::create([
            'empresa_id' => $empresa->id, 'descricao_empresa' => 'Agenda online', 'tipo_cliente' => 'Salões',
            'latitude' => -26.9, 'longitude' => -49.0, 'radius_km' => 5, 'keywords' => [], 'status' => 'done',
        ]);
    }

    private function empresa(string $nome): Empresa
    {
        return Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => $nome]);
    }
}
