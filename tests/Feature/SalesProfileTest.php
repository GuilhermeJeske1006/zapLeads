<?php

namespace Tests\Feature;

use App\Livewire\Leads\LeadsTable;
use App\Livewire\SalesProfile;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** "Minha Empresa → Vendas": the facts the AI scores leads and writes outreach with. */
class SalesProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->empresa = Empresa::create(['user_id' => $this->user->id, 'nome' => 'Agenda Fácil', 'slug' => 'agenda-facil']);
    }

    public function test_saves_the_sales_profile_with_one_proof_per_line(): void
    {
        Livewire::test(SalesProfile::class, ['empresa' => $this->empresa])
            ->set('ofertaPrincipal', '  Agenda com confirmação pelo WhatsApp ')
            ->set('problemaQueResolve', 'Clientes que faltam')
            ->set('provasSociais', "120 salões em SC usam o sistema\r\n\n  Studio X reduziu as faltas em 30%  \n")
            ->set('ofertaDeEntrada', 'Demo de 10 minutos')
            ->set('segmentosExcluidos', 'franquias')
            ->set('diferencial', '')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSet('provasSociais', "120 salões em SC usam o sistema\nStudio X reduziu as faltas em 30%")
            ->assertDispatched('toast', type: 'success');

        $this->empresa->refresh();
        $this->assertSame('Agenda com confirmação pelo WhatsApp', $this->empresa->oferta_principal);
        $this->assertSame(['120 salões em SC usam o sistema', 'Studio X reduziu as faltas em 30%'], $this->empresa->provas_sociais);
        $this->assertSame('franquias', $this->empresa->segmentos_excluidos);
        $this->assertNull($this->empresa->diferencial);
    }

    public function test_rejects_more_than_ten_proofs(): void
    {
        Livewire::test(SalesProfile::class, ['empresa' => $this->empresa])
            ->set('provasSociais', implode("\n", array_map(fn (int $i) => "Case {$i}", range(1, 11))))
            ->call('salvar')
            ->assertHasErrors('provasSociais');

        $this->assertNull($this->empresa->fresh()->provas_sociais);
    }

    public function test_empresa_page_shows_the_sales_section(): void
    {
        $this->actingAs($this->user)
            ->get(route('empresa.edit'))
            ->assertOk()
            ->assertSeeLivewire(SalesProfile::class)
            ->assertSee(__('messages.sales_profile_desc'));
    }

    public function test_lead_modal_explains_the_score(): void
    {
        $lead = Lead::create([
            'empresa_id'  => $this->empresa->id,
            'nome'        => 'Studio Bella',
            'telefone'    => '',
            'source'      => 'internet',
            'lead_score'  => 66,
            'ai_insights' => [
                'gancho'          => 'Avaliação recente diz que nunca respondem o WhatsApp',
                'score_breakdown' => ['fit' => 85, 'contatabilidade' => 70, 'dor' => 70, 'proximidade' => null, 'contatabilidade_estimada' => true],
            ],
        ]);

        Livewire::test(LeadsTable::class, ['empresa' => $this->empresa])
            ->assertSeeHtml('title="' . e(__('messages.score_hint')) . '"')
            ->call('abrirModal', $lead->id)
            ->assertSee(__('messages.score_breakdown_title'))
            ->assertSee(__('messages.score_contact_estimated'))
            ->assertSee(__('messages.score_unknown'))
            ->assertSee('Avaliação recente diz que nunca respondem o WhatsApp');
    }
}
