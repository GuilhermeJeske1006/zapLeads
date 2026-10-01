<?php

namespace Tests\Feature\Enrichment;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\User;
use App\Services\Enrichment\LeadEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CnpjStepTest extends TestCase
{
    use RefreshDatabase;

    private const CNPJ = '11222333000181';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_places.key' => '']);
        Http::preventStrayRequests();
    }

    public function test_decision_maker_is_the_managing_partner(): void
    {
        Http::fake(['brasilapi.com.br/api/cnpj/v1/' . self::CNPJ => Http::response($this->receita())]);
        $lead = $this->lead();

        app(LeadEnrichmentService::class)->enrich($lead);

        $lead->refresh();
        $this->assertSame('Maria de Souza', $lead->decisor_nome);
        $this->assertSame('Sócio-Administrador', $lead->decisor_cargo);
        $this->assertSame('ME', $lead->porte);
        $this->assertSame('ATIVA', $lead->situacao_cadastral);
        $this->assertSame('2015-03-10', $lead->data_abertura->toDateString());
        $this->assertSame('9602-5/01 - Cabeleireiros, manicure e pedicure', $lead->dossie['cnae']);

        // ME: the registry mobile is probably the owner's. 55 + 5.
        $mobile = $lead->contacts()->where('valor_e164', '+5547999990000')->sole();
        $this->assertSame([60, true, 'cnpj_receita'], [$mobile->confianca, $mobile->provavel_decisor, $mobile->origem]);
        $this->assertSame('financeiro@contabil.com.br', $lead->email);
    }

    public function test_single_partner_or_mei_owner_is_the_decision_maker(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::sequence()
            ->push($this->receita(['qsa' => [['nome_socio' => 'JOÃO DOS SANTOS', 'codigo_qualificacao_socio' => 22, 'qualificacao_socio' => 'Sócio']]]))
            ->push($this->receita(['qsa' => [], 'opcao_pelo_mei' => true, 'razao_social' => '45.678.901 ANA PAULA DA SILVA'])),
        ]);

        app(LeadEnrichmentService::class)->enrich($single = $this->lead());
        app(LeadEnrichmentService::class)->enrich($mei = $this->lead('45678901000102'));

        $this->assertSame('João dos Santos', $single->fresh()->decisor_nome);
        $this->assertSame(['Ana Paula da Silva', 'Titular (MEI)', 'MEI'], [$mei->fresh()->decisor_nome, $mei->fresh()->decisor_cargo, $mei->fresh()->porte]);
    }

    public function test_inactive_company_is_discarded(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response($this->receita(['descricao_situacao_cadastral' => 'BAIXADA']))]);
        $lead = $this->lead();

        app(LeadEnrichmentService::class)->enrich($lead);

        $lead->refresh();
        $this->assertSame('descartado', $lead->status);
        $this->assertSame('cnpj_baixada', $lead->dossie['descartado']);
        $this->assertSame(0, $lead->contacts()->count());
    }

    public function test_falls_back_to_the_next_provider_and_caches_the_answer(): void
    {
        Http::fake([
            'brasilapi.com.br/*' => Http::response('', 503),
            'minhareceita.org/*' => Http::response($this->receita()),
        ]);

        app(LeadEnrichmentService::class)->enrich($this->lead());
        app(LeadEnrichmentService::class)->enrich($second = $this->lead());

        $this->assertSame('Maria de Souza', $second->fresh()->decisor_nome);
        Http::assertSentCount(2); // BrasilAPI failed, Minha Receita answered; the second lead used the cache
    }

    private function receita(array $overrides = []): array
    {
        return [
            'cnpj'                         => self::CNPJ,
            'razao_social'                 => 'STUDIO BELLA LTDA',
            'nome_fantasia'                => 'STUDIO BELLA',
            'porte'                        => 'MICRO EMPRESA',
            'opcao_pelo_mei'               => false,
            'descricao_situacao_cadastral' => 'ATIVA',
            'data_inicio_atividade'        => '2015-03-10',
            'cnae_fiscal'                  => '9602-5/01',
            'cnae_fiscal_descricao'        => 'Cabeleireiros, manicure e pedicure',
            'ddd_telefone_1'               => '4799990000',
            'ddd_telefone_2'               => '',
            'email'                        => 'FINANCEIRO@CONTABIL.COM.BR',
            'qsa'                          => [
                ['nome_socio' => 'JOAO DA SILVA', 'codigo_qualificacao_socio' => 22, 'qualificacao_socio' => 'Sócio'],
                ['nome_socio' => 'MARIA DE SOUZA', 'codigo_qualificacao_socio' => 49, 'qualificacao_socio' => 'Sócio-Administrador'],
            ],
            ...$overrides,
        ];
    }

    private function lead(string $cnpj = self::CNPJ): Lead
    {
        $empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);

        return Lead::create(['empresa_id' => $empresa->id, 'nome' => 'Studio Bella', 'telefone' => '', 'cnpj' => $cnpj]);
    }
}
