<?php

namespace Tests\Feature;

use App\Livewire\Leads\PipelineBoard;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\User;
use App\Services\Prospecting\FollowUpCadence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PipelineBoardTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);
    }

    public function test_columns_follow_the_funnel(): void
    {
        $this->lead('Novo com score', ['lead_score' => 90]);
        $this->lead('Abordado', ['status' => 'abordado']);
        $this->lead('Na proposta', ['status' => 'proposta']);

        Livewire::test(PipelineBoard::class, ['empresa' => $this->empresa])
            ->assertViewHas('columns', fn (array $columns) => array_keys($columns) === array_keys(Lead::STATUSES)
                && $columns['novo']->pluck('nome')->all() === ['Novo com score']
                && $columns['abordado']->pluck('nome')->all() === ['Abordado']
                && $columns['proposta']->pluck('nome')->all() === ['Na proposta']
                && $columns['convertido']->isEmpty())
            ->assertSeeHtml('wire:sort:group-id="reuniao"');
    }

    public function test_card_dropped_in_another_column_changes_the_status(): void
    {
        $lead = $this->lead('Studio Bella', ['status' => 'respondeu']);

        Livewire::test(PipelineBoard::class, ['empresa' => $this->empresa])
            ->call('mover', (string) $lead->id, 0, 'reuniao')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('reuniao', $lead->fresh()->status);
    }

    public function test_keyboard_move_ends_the_cold_cadence(): void
    {
        $lead = $this->lead('Studio Bella', ['status' => 'abordado']);
        $sequence = Sequence::create(['empresa_id' => $this->empresa->id, 'nome' => 'Prospecção fria', 'trigger' => FollowUpCadence::TRIGGER, 'status' => 'active']);
        $enrollment = SequenceEnrollment::create(['sequence_id' => $sequence->id, 'lead_id' => $lead->id, 'current_step' => 2, 'status' => 'active', 'next_send_at' => now()]);
        $followUp = OutreachDraft::create(['empresa_id' => $this->empresa->id, 'lead_id' => $lead->id, 'etapa' => 2, 'status' => 'generating']);

        Livewire::test(PipelineBoard::class, ['empresa' => $this->empresa])->call('moverPara', $lead->id, 'descartado');

        $this->assertSame('descartado', $lead->fresh()->status);
        $this->assertSame('stopped', $enrollment->fresh()->status);
        $this->assertSame('skipped', $followUp->fresh()->status);
    }

    public function test_unknown_status_and_other_empresa_leads_are_ignored(): void
    {
        $lead = $this->lead('Studio Bella');
        $other = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Outra']);
        $foreign = Lead::create(['empresa_id' => $other->id, 'nome' => 'Lead alheio', 'telefone' => '']);

        Livewire::test(PipelineBoard::class, ['empresa' => $this->empresa])
            ->call('mover', $lead->id, 0, 'contatado')
            ->call('mover', $foreign->id, 0, 'convertido')
            ->assertNotDispatched('toast')
            ->assertDontSee('Lead alheio');

        $this->assertSame(['novo', 'novo'], [$lead->fresh()->status, $foreign->fresh()->status]);
    }

    public function test_filters_by_origin_and_name(): void
    {
        $this->lead('Studio Bella');
        $this->lead('Cliente do catálogo', ['source' => 'internal']);

        Livewire::test(PipelineBoard::class, ['empresa' => $this->empresa])
            ->set('origem', 'catalogo')
            ->assertSee('Cliente do catálogo')
            ->assertDontSee('Studio Bella')
            ->set('origem', '')
            ->set('busca', 'Bella')
            ->assertSee('Studio Bella')
            ->assertDontSee('Cliente do catálogo');
    }

    public function test_full_columns_link_to_the_filtered_list(): void
    {
        foreach (range(1, PipelineBoard::PER_COLUMN + 1) as $i) {
            $this->lead("Lead {$i}", ['status' => 'abordado']);
        }

        Livewire::test(PipelineBoard::class, ['empresa' => $this->empresa])
            ->assertSeeHtml('href="' . e(route('leads.index', ['status' => 'abordado'])) . '"');
    }

    private function lead(string $nome, array $attributes = []): Lead
    {
        return Lead::create($attributes + [
            'empresa_id' => $this->empresa->id,
            'nome'       => $nome,
            'telefone'   => '(47) 99280-1006',
            'source'     => 'internet',
        ]);
    }
}
