<?php

namespace App\Services\Prospecting;

use App\Jobs\DraftFollowUpJob;
use App\Jobs\GenerateOutreachDraftJob;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;

/**
 * The cold cadence after the first message: D2 value, D5 another angle, D10 goodbye, each counted
 * from when the previous message actually went out. A due step becomes a draft in the review queue
 * like any prospect message (nothing goes out unreviewed). Any reply, opt-out or a lead marked
 * interested, converted or discarded ends it.
 */
class FollowUpCadence
{
    public const TRIGGER = 'prospecting';

    /** ordem => goal and wait after the previous message. */
    public const STEPS = [
        1 => ['objetivo' => 'valor', 'delay_hours' => 48, 'mensagem' => 'D2: valor (um insight curto ou um caso real, sem cobrar resposta)'],
        2 => ['objetivo' => 'novo_angulo', 'delay_hours' => 72, 'mensagem' => 'D5: outra pergunta, por um ângulo diferente'],
        3 => ['objetivo' => 'encerramento', 'delay_hours' => 120, 'mensagem' => 'D10: encerramento (avisa que vai parar e deixa a porta aberta)'],
    ];

    /** Lead statuses that end the cadence: the conversation moved on, or the user gave up on the lead. */
    private const FINAL_STATUSES = ['interessado', 'convertido', 'descartado'];

    /** The empresa's "Prospecção fria" sequence, created with the default steps the first time. */
    public function sequenceFor(Empresa $empresa): Sequence
    {
        $sequence = Sequence::firstOrCreate(
            ['empresa_id' => $empresa->id, 'trigger' => self::TRIGGER],
            ['nome' => 'Prospecção fria', 'status' => 'active'],
        );

        if ($sequence->wasRecentlyCreated) {
            foreach (self::STEPS as $ordem => $step) {
                $sequence->steps()->create(['ordem' => $ordem, 'tipo' => 'ai', ...$step]);
            }
        }

        return $sequence;
    }

    /** A prospect message went out (API or the user's own WhatsApp): the next step is scheduled from now. */
    public function onSent(OutreachDraft $draft): void
    {
        if ($draft->etapa === 0) {
            $sequence = $this->sequenceFor($draft->empresa);
            if ($sequence->status !== 'active') {
                return;
            }

            $enrollment = SequenceEnrollment::updateOrCreate(
                ['sequence_id' => $sequence->id, 'lead_id' => $draft->lead_id],
                ['current_step' => 0, 'status' => 'active', 'next_send_at' => now()],
            );
        } else {
            $enrollment = $draft->enrollment;
        }

        if ($enrollment?->status === 'active') {
            $this->scheduleAfter($enrollment, $draft->etapa);
        }
    }

    /** The user skipped a follow-up: the cadence moves on to the next step. */
    public function onSkipped(OutreachDraft $draft): void
    {
        if ($draft->isFollowUp() && $draft->enrollment?->status === 'active') {
            $this->scheduleAfter($draft->enrollment, $draft->etapa);
        }
    }

    /**
     * DraftFollowUpJob: the step is due. Puts the follow-up in the review queue, unless the cadence
     * ended or moved on since it was scheduled.
     */
    public function due(SequenceEnrollment $enrollment, int $step): ?OutreachDraft
    {
        if ($enrollment->status !== 'active' || $enrollment->current_step !== $step) {
            return null;
        }

        $lead = $enrollment->lead;
        if ($lead->isOptedOut()) {
            $enrollment->update(['status' => 'opted_out']);
            return null;
        }
        if (in_array($lead->status, self::FINAL_STATUSES, true)) {
            $enrollment->update(['status' => 'stopped']);
            return null;
        }

        $pending = OutreachDraft::where('lead_id', $lead->id)->whereIn('status', OutreachDraft::PENDING)->exists();
        if ($pending) {
            return null;
        }

        $previous = OutreachDraft::where('lead_id', $lead->id)->where('status', 'sent')->latest('id')->first();

        $draft = OutreachDraft::create([
            'empresa_id'             => $lead->empresa_id,
            'lead_id'                => $lead->id,
            'whatsapp_channel_id'    => $previous?->whatsapp_channel_id,
            'etapa'                  => $step,
            'sequence_enrollment_id' => $enrollment->id,
            'status'                 => 'generating',
        ]);

        GenerateOutreachDraftJob::dispatch($draft->id);

        return $draft;
    }

    /** Ends the lead's cadence and drops follow-ups still waiting for review. */
    public function stop(Lead $lead, string $status = 'replied'): void
    {
        SequenceEnrollment::where('lead_id', $lead->id)
            ->where('status', 'active')
            ->whereHas('sequence', fn ($q) => $q->where('trigger', self::TRIGGER))
            ->update(['status' => $status]);

        OutreachDraft::where('lead_id', $lead->id)
            ->where('etapa', '>', 0)
            ->whereIn('status', OutreachDraft::PENDING)
            ->update(['status' => 'skipped']);
    }

    /** The goal of a follow-up draft's step (valor, novo_angulo, encerramento). */
    public function goalOf(OutreachDraft $draft): string
    {
        $step = $draft->enrollment?->sequence?->steps->firstWhere('ordem', $draft->etapa);

        return $step?->objetivo ?? self::STEPS[$draft->etapa]['objetivo'] ?? 'novo_angulo';
    }

    private function scheduleAfter(SequenceEnrollment $enrollment, int $doneStep): void
    {
        $next = $enrollment->sequence->steps->firstWhere('ordem', $doneStep + 1);

        if (!$next) {
            $enrollment->update(['status' => 'completed', 'current_step' => $doneStep]);
            return;
        }

        $at = now()->addHours($next->delay_hours);
        $enrollment->update(['current_step' => $next->ordem, 'next_send_at' => $at]);

        DraftFollowUpJob::dispatch($enrollment->id, $next->ordem)->delay($at);
    }
}
