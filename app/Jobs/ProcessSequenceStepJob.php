<?php

namespace App\Jobs;

use App\Models\SequenceEnrollment;
use App\Services\WhatsAppService;
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

    public function __construct(
        private SequenceEnrollment $enrollment,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
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
        $step = $steps->get($enrollment->current_step);

        if (!$step) {
            $enrollment->update(['status' => 'completed']);
            return;
        }

        $text = str_replace(
            ['{nome}', '{loja}'],
            [$lead->nome, $lead->loja->nome],
            $step->mensagem
        );

        $result = $step->tipo === 'image' && $step->imagem
            ? $whatsApp->sendImageMessage($lead->telefone, asset('storage/' . $step->imagem), $text)
            : $whatsApp->sendTextMessage($lead->telefone, $text);

        if (!$result['success']) {
            Log::warning('ProcessSequenceStepJob send failed', [
                'enrollment_id' => $enrollment->id,
                'step' => $enrollment->current_step,
                'error' => $result['error'] ?? null,
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
