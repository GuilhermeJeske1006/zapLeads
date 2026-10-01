<?php

namespace Tests\Feature;

use App\Jobs\GenerateOutreachDraftJob;
use App\Livewire\Leads\InternetProspector;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class InternetProspectorSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_the_message_for_review_without_waiting_for_the_ai(): void
    {
        [$empresa, $lead] = $this->empresaWithLead();

        Livewire::test(InternetProspector::class, ['empresa' => $empresa])
            ->call('gerarAbordagem', $lead->id)
            ->assertDispatched('outreach-requested')
            ->assertDispatched('toast', type: 'success', message: __('messages.outreach_requested', ['nome' => 'Studio Bella']));

        $this->assertSame('generating', OutreachDraft::sole()->status);
        $this->assertDatabaseCount('conversations', 0);
        Queue::assertPushed(GenerateOutreachDraftJob::class);
    }

    public function test_does_not_approach_opted_out_lead(): void
    {
        [$empresa, $lead] = $this->empresaWithLead();
        $lead->update(['opted_out_at' => now()]);

        Livewire::test(InternetProspector::class, ['empresa' => $empresa])
            ->call('gerarAbordagem', $lead->id)
            ->assertDispatched('toast', type: 'error', message: __('messages.lead_opted_out'));

        $this->assertDatabaseCount('outreach_drafts', 0);
        Queue::assertNothingPushed();
    }

    public function test_does_not_approach_lead_with_unreadable_phone(): void
    {
        [$empresa, $lead] = $this->empresaWithLead();
        $lead->update(['telefone' => '9280-1006']);

        Livewire::test(InternetProspector::class, ['empresa' => $empresa])
            ->call('gerarAbordagem', $lead->id)
            ->assertDispatched('toast', type: 'error', message: __('messages.lead_invalid_phone'));

        $this->assertDatabaseCount('outreach_drafts', 0);
    }

    /** @return array{Empresa, Lead} */
    private function empresaWithLead(): array
    {
        Queue::fake();

        $ai = Mockery::mock(AIService::class);
        $ai->shouldNotReceive('gerarPrimeiraMensagemProspeccao');
        $this->app->instance(AIService::class, $ai);

        $empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Empresa']);
        $lead = Lead::create(['empresa_id' => $empresa->id, 'nome' => 'Studio Bella', 'telefone' => '+55 47 99691-8841']);

        return [$empresa, $lead];
    }
}
