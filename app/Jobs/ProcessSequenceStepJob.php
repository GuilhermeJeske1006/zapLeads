<?php

namespace App\Jobs;

use App\Models\SequenceEnrollment;
use App\Services\LeadMessenger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSequenceStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        private SequenceEnrollment $enrollment,
    ) {}

    public function handle(LeadMessenger $messenger): void
    {
        $enrollment = $this->enrollment->fresh();

        if ($enrollment->status !== 'active') {
            return;
        }

        $lead = $enrollment->lead;

        if ($lead->isOptedOut()) {
            $enrollment->update(['status' => 'opted_out']);
            return;
        }

        $steps = $enrollment->sequence->steps()->orderBy('ordem')->get();
        $step  = $steps->get($enrollment->current_step);

        if (!$step) {
            $enrollment->update(['status' => 'completed']);
            return;
        }

        $text = str_replace(
            ['{nome}', '{loja}'],
            [$lead->nome, $lead->empresa?->nome],
            $step->mensagem
        );

        // Outside the 24h session only an approved template goes; without one the step is skipped.
        $outcome = $messenger->send($lead, $text, $step->tipo === 'image' && $step->imagem ? asset('storage/' . $step->imagem) : null);

        if ($outcome === LeadMessenger::OPTED_OUT) {
            $enrollment->update(['status' => 'opted_out']);
            return;
        }

        if (!in_array($outcome, [LeadMessenger::SENT, LeadMessenger::TEMPLATE], true)) {
            Log::warning('ProcessSequenceStepJob step not sent', [
                'enrollment_id' => $enrollment->id,
                'step'          => $enrollment->current_step,
                'outcome'       => $outcome,
            ]);
        }

        $nextStep = $steps->get($enrollment->current_step + 1);

        if ($nextStep) {
            $enrollment->update([
                'current_step' => $enrollment->current_step + 1,
                'next_send_at' => now()->addHours($nextStep->delay_hours),
            ]);
            self::dispatch($enrollment)->delay(now()->addHours($nextStep->delay_hours));
        } else {
            $enrollment->update(['status' => 'completed']);
        }
    }
}
