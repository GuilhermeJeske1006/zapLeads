<?php

namespace Tests\Feature;

use App\Jobs\FindInternetLeadsJob;
use App\Livewire\Leads\InternetProspector;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\ProspectingSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class InternetProspectorSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_polls_its_own_search_even_when_another_finishes_later(): void
    {
        Queue::fake();
        $empresa = $this->empresa();

        $component = Livewire::test(InternetProspector::class, ['empresa' => $empresa])->call('buscar');

        $mine = ProspectingSearch::sole();
        $this->assertSame('queued', $mine->status);
        Queue::assertPushed(FindInternetLeadsJob::class, fn (FindInternetLeadsJob $job) => $job->searchId === $mine->id);

        // Job finishes; meanwhile a search from another tab finishes too.
        $mine->update(['status' => 'done', 'results_count' => 1]);
        $myLead = $this->lead($empresa, $mine, 'Padaria Central');
        $other = $mine->replicate()->fill(['status' => 'done']);
        $other->save();
        $this->lead($empresa, $other, 'Confeitaria Doce');

        $component->call('pollSearch')->assertSet('buscando', false);

        $this->assertSame([$myLead->id], array_column($component->get('resultados'), 'id'));
    }

    public function test_keeps_polling_while_search_is_queued(): void
    {
        Queue::fake();

        Livewire::test(InternetProspector::class, ['empresa' => $this->empresa()])
            ->call('buscar')
            ->call('pollSearch')
            ->assertSet('buscando', true);
    }

    public function test_search_id_cannot_be_set_from_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(InternetProspector::class, ['empresa' => $this->empresa()])->set('searchId', 1);
    }

    public function test_crashed_job_marks_search_failed(): void
    {
        $search = ProspectingSearch::create([
            'empresa_id'        => $this->empresa()->id,
            'descricao_empresa' => 'Fornecedor de embalagens',
            'tipo_cliente'      => 'Padarias',
            'latitude'          => -26.9,
            'longitude'         => -49.0,
            'radius_km'         => 5,
            'status'            => 'running',
        ]);

        (new FindInternetLeadsJob($search->id))->failed(new RuntimeException('timeout'));

        $this->assertSame('failed', $search->fresh()->status);
    }

    private function lead(Empresa $empresa, ProspectingSearch $search, string $nome): Lead
    {
        return Lead::create([
            'empresa_id'            => $empresa->id,
            'prospecting_search_id' => $search->id,
            'nome'                  => $nome,
            'telefone'              => '',
            'source'                => 'internet',
        ]);
    }

    private function empresa(): Empresa
    {
        return Empresa::create([
            'user_id'           => User::factory()->create()->id,
            'nome'              => 'Embalagens SC',
            'endereco'          => 'Rua XV de Novembro, 100',
            'cidade'            => 'Blumenau',
            'latitude'          => -26.9,
            'longitude'         => -49.0,
            'descricao_empresa' => 'Fornecedor de embalagens para alimentos há dez anos.',
            'tipo_cliente_alvo' => 'Padarias e confeitarias',
            'raio_atendimento'  => 5,
        ]);
    }
}
