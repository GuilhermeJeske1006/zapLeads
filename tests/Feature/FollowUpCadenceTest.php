<?php

namespace Tests\Feature;

use App\Jobs\DraftFollowUpJob;
use App\Jobs\GenerateOutreachDraftJob;
use App\Livewire\Leads\OutreachQueue;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\SequenceEnrollment;
use App\Models\User;
use App\Services\AIService;
use App\Services\Prospecting\FollowUpCadence;
use App\Services\Prospecting\OutreachService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/** D0 first message → D2 value → D5 another angle → D10 goodbye, each reviewed, until the lead answers. */
class FollowUpCadenceTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);
        $this->lead = Lead::create(['empresa_id' => $this->empresa->id, 'nome' => 'Studio Bella', 'telefone' => '+55 47 99999-8888', 'source' => 'internet']);
    }

    public function test_sending_the_first_message_starts_the_cadence_two_days_later(): void
    {
        $this->sendFirst();

        $sequence = app(FollowUpCadence::class)->sequenceFor($this->empresa);
        $this->assertSame(['valor', 'novo_angulo', 'encerramento'], $sequence->steps->pluck('objetivo')->all());
        $enrollment = SequenceEnrollment::sole();
        $this->assertSame(['active', 1], [$enrollment->status, $enrollment->current_step]);
        Queue::assertPushed(DraftFollowUpJob::class, fn (DraftFollowUpJob $job) => $job->step === 1
            && $job->enrollmentId === $enrollment->id
            && abs($job->delay->diffInHours(now()->addHours(48))) < 1);
    }

    public function test_due_step_goes_to_review_written_for_its_goal(): void
    {
        $this->sendFirst();
        $this->mock(AIService::class, fn (MockInterface $ai) => $ai->shouldReceive('gerarFollowUp')->once()
            ->withArgs(fn ($e, $l, $objetivo, $historico) => $objetivo === 'valor'
                && $historico === ['Oi! Vocês ainda marcam horário só pelo WhatsApp?']
                && $l['dor_hipotese'] === 'Perde clientes sem resposta')
            ->andReturn('Uma ideia: confirmar o horário pelo WhatsApp na véspera reduz as faltas.'));

        $draft = app(FollowUpCadence::class)->due(SequenceEnrollment::sole(), 1);

        $this->assertSame([1, 'generating'], [$draft->etapa, $draft->status]);
        Queue::assertPushed(GenerateOutreachDraftJob::class, fn ($job) => $job->draftId === $draft->id);

        (new GenerateOutreachDraftJob($draft->id))->handle(app(OutreachService::class));

        $draft->refresh();
        $this->assertSame(['draft', 'valor'], [$draft->status, $draft->variante_escolhida]);
        $this->assertSame('Uma ideia: confirmar o horário pelo WhatsApp na véspera reduz as faltas.', $draft->texto_final);
    }

    public function test_each_follow_up_sent_schedules_the_next_and_the_last_ends_it(): void
    {
        $this->sendFirst();
        $enrollment = SequenceEnrollment::sole();
        $outreach = app(OutreachService::class);

        $outreach->markAssisted($this->followUp($enrollment, 1), 'Uma ideia rápida.');
        $this->assertSame(2, $enrollment->fresh()->current_step);
        Queue::assertPushed(DraftFollowUpJob::class, fn (DraftFollowUpJob $job) => $job->step === 2
            && abs($job->delay->diffInHours(now()->addHours(72))) < 1);

        $outreach->markAssisted($this->followUp($enrollment, 2), 'Outra pergunta?');
        $outreach->markAssisted($this->followUp($enrollment, 3), 'Vou parar por aqui.');

        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertSame([0, 1, 2, 3], $this->lead->outreachAttempts()->orderBy('id')->pluck('etapa')->all());
    }

    public function test_any_reply_ends_the_cadence_and_drops_follow_ups_in_review(): void
    {
        $this->sendFirst();
        $enrollment = SequenceEnrollment::sole();
        $pending = $this->followUp($enrollment, 1);

        app(OutreachService::class)->markReplied($this->empresa->id, '+5547999998888');

        $this->assertSame('replied', $enrollment->fresh()->status);
        $this->assertSame('skipped', $pending->fresh()->status);
        $this->assertNull(app(FollowUpCadence::class)->due($enrollment->fresh(), 1));
    }

    public function test_he_replied_in_the_review_queue_stops_the_follow_ups(): void
    {
        $this->sendFirst();
        $draft = $this->followUp(SequenceEnrollment::sole(), 1);

        Livewire::test(OutreachQueue::class, ['empresa' => $this->empresa])
            ->assertSee(__('messages.outreach_follow_up_step', ['step' => 1, 'total' => 3, 'goal' => __('messages.outreach_goal_valor')]))
            ->call('respondeu', $draft->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('replied', SequenceEnrollment::sole()->status);
        $this->assertSame('skipped', $draft->fresh()->status);
        $this->assertNotNull($this->lead->outreachAttempts()->first()->responded_at);
    }

    public function test_lead_given_up_on_or_opted_out_gets_no_more_follow_ups(): void
    {
        $this->sendFirst();
        $enrollment = SequenceEnrollment::sole();
        $cadence = app(FollowUpCadence::class);

        $this->lead->update(['status' => 'descartado']);
        $this->assertNull($cadence->due($enrollment, 1));
        $this->assertSame('stopped', $enrollment->fresh()->status);

        $enrollment->update(['status' => 'active']);
        $this->lead->update(['status' => 'contatado', 'opted_out_at' => now()]);
        $this->assertNull($cadence->due($enrollment->fresh(), 1));
        $this->assertSame('opted_out', $enrollment->fresh()->status);
        $this->assertSame(1, OutreachDraft::count());
    }

    public function test_skipping_a_follow_up_moves_to_the_next_step_and_stale_jobs_do_nothing(): void
    {
        $this->sendFirst();
        $enrollment = SequenceEnrollment::sole();

        app(OutreachService::class)->skip($this->followUp($enrollment, 1));

        $this->assertSame(2, $enrollment->fresh()->current_step);
        $this->assertNull(app(FollowUpCadence::class)->due($enrollment->fresh(), 1));
    }

    private function sendFirst(): void
    {
        $draft = OutreachDraft::create([
            'empresa_id' => $this->empresa->id, 'lead_id' => $this->lead->id, 'status' => 'draft',
            'texto_final' => 'Oi! Vocês ainda marcam horário só pelo WhatsApp?', 'variante_escolhida' => 'dor_do_segmento',
            'dor_hipotese' => 'Perde clientes sem resposta',
        ]);

        app(OutreachService::class)->markAssisted($draft, $draft->texto_final);
    }

    private function followUp(SequenceEnrollment $enrollment, int $etapa): OutreachDraft
    {
        return OutreachDraft::create([
            'empresa_id' => $this->empresa->id, 'lead_id' => $this->lead->id, 'status' => 'draft', 'etapa' => $etapa,
            'sequence_enrollment_id' => $enrollment->id, 'texto_final' => 'Follow-up?',
            'variante_escolhida' => FollowUpCadence::STEPS[$etapa]['objetivo'],
        ]);
    }
}
