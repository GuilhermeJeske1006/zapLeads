<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\User;
use App\Services\AIService;
use App\Services\Prospecting\OutreachWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class OutreachWriterTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'user_id'           => User::factory()->create()->id,
            'nome'              => 'Agenda Fácil',
            'oferta_principal'  => 'Agenda online com confirmação pelo WhatsApp',
            'oferta_de_entrada' => 'Demo de 10 minutos',
            'provas_sociais'    => ['120 salões usam o sistema'],
        ]);
        $this->lead = Lead::create([
            'empresa_id'   => $this->empresa->id,
            'nome'         => 'Studio Bella',
            'telefone'     => '+55 47 99999-8888',
            'email'        => 'contato@studiobella.com.br',
            'cidade'       => 'Blumenau',
            'decisor_nome' => 'Carla Souza',
            'ai_insights'  => ['rating' => 4.8, 'user_ratings_total' => 120, 'gancho' => 'Nota 4,8 com 120 avaliações no Google'],
        ]);
    }

    public function test_only_failing_angles_are_rewritten_once_with_the_reasons(): void
    {
        $this->mock(AIService::class, function (MockInterface $ai) {
            $ai->shouldReceive('gerarAbordagem')->once()->withArgs(fn ($e, $l, $idioma, $correcoes = []) => $correcoes === [])->andReturn($this->written([
                'observacao'      => 'Oi, Carla! Vi a nota 4,8 do Studio Bella no Google. Seria absurdo te mostrar uma ideia?',
                'dor_do_segmento' => 'Salões perdem 40% dos clientes no fim de semana. Você se opõe a ver como evitar?',
                'roteamento'      => 'É com você que falo sobre a agenda do Studio Bella, ou tem outra pessoa?',
            ]));
            $ai->shouldReceive('gerarAbordagem')->once()
                ->withArgs(fn ($e, $l, $idioma, $correcoes = []) => $correcoes === ['variante dor_do_segmento: cita o número 40, que não está nos dados'])
                ->andReturn($this->written([
                    'observacao'      => 'Texto que não deve substituir o primeiro?',
                    'dor_do_segmento' => 'Muito salão perde cliente no fim de semana. Você se opõe a ver como evitar?',
                    'roteamento'      => 'Outro roteamento?',
                ]));
        });

        $written = app(OutreachWriter::class)->firstMessage($this->lead);

        $this->assertSame([
            'Oi, Carla! Vi a nota 4,8 do Studio Bella no Google. Seria absurdo te mostrar uma ideia?',
            'Muito salão perde cliente no fim de semana. Você se opõe a ver como evitar?',
            'É com você que falo sobre a agenda do Studio Bella, ou tem outra pessoa?',
        ], array_column($written['variantes'], 'mensagem'));
    }

    public function test_variants_that_fail_twice_are_dropped_and_none_left_means_null(): void
    {
        $bad = $this->written(['observacao' => 'Temos 500 clientes. Topa?', 'dor_do_segmento' => 'Sem pergunta.', 'roteamento' => 'Veja www.agenda.com.br?']);
        $this->mock(AIService::class, fn (MockInterface $ai) => $ai->shouldReceive('gerarAbordagem')->twice()->andReturn($bad));

        $this->assertNull(app(OutreachWriter::class)->firstMessage($this->lead));
    }

    public function test_lead_data_has_no_contacts_and_names_the_decision_maker_only_from_the_registry(): void
    {
        $data = OutreachWriter::leadData($this->lead);
        $sent = json_encode($data, JSON_UNESCAPED_UNICODE);

        $this->assertSame('Carla', $data['decisor_primeiro_nome']);
        $this->assertSame('Nota 4,8 com 120 avaliações no Google', $data['gancho']);
        $this->assertStringNotContainsString('99999', $sent);
        $this->assertStringNotContainsString('contato@', $sent);
        $this->assertArrayNotHasKey('id', $data);

        // A name found on a web page is not trusted enough to greet someone by.
        $this->lead->dossie = ['decisor_fonte' => 'https://guia.com/studio-bella'];
        $this->assertArrayNotHasKey('decisor_primeiro_nome', OutreachWriter::leadData($this->lead));
    }

    public function test_follow_up_is_rewritten_once_and_may_repeat_what_was_already_sent(): void
    {
        $this->mock(AIService::class, function (MockInterface $ai) {
            $ai->shouldReceive('gerarFollowUp')->once()
                ->withArgs(fn ($e, $l, $objetivo, $historico) => $objetivo === 'valor' && $historico === ['Primeira com demo de 15 min?'] && $l['dor_hipotese'] === 'Faltas')
                ->andReturn('Um salão reduziu as faltas em 30%. Vale uma demo de 15 min.');
            $ai->shouldReceive('gerarFollowUp')->once()
                ->withArgs(fn ($e, $l, $o, $h, $idioma, $correcoes = []) => $correcoes === ['cita o número 30, que não está nos dados'])
                ->andReturn('Um salão parecido acabou com as faltas confirmando pelo WhatsApp. Vale uma demo de 15 min.');
        });

        $text = app(OutreachWriter::class)->followUp($this->lead, 'valor', ['Primeira com demo de 15 min?'], 'Faltas');

        $this->assertSame('Um salão parecido acabou com as faltas confirmando pelo WhatsApp. Vale uma demo de 15 min.', $text);
    }

    private function written(array $byAngle): array
    {
        return [
            'dor_hipotese' => 'Clientes desistem quando ninguém responde',
            'variantes'    => array_map(fn ($angulo, $mensagem) => ['angulo' => $angulo, 'mensagem' => $mensagem, 'gancho_usado' => null], array_keys($byAngle), $byAngle),
        ];
    }
}
