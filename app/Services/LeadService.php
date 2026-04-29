<?php

namespace App\Services;

use App\Jobs\FollowUpWhatsAppJob;
use App\Jobs\ProcessSequenceStepJob;
use App\Models\Lead;
use App\Models\Loja;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;

class LeadService
{
    public function __construct(
        private GeoService $geoService,
    ) {}

    private function enrollInSequences(Loja $loja, Lead $lead): void
    {
        $sequences = Sequence::where('loja_id', $loja->id)
            ->where('status', 'active')
            ->where('trigger', 'lead_capture')
            ->with(['steps' => fn ($q) => $q->orderBy('ordem')])
            ->get();

        foreach ($sequences as $sequence) {
            $firstStep = $sequence->steps->first();
            if (!$firstStep) continue;

            $enrollment = SequenceEnrollment::firstOrCreate(
                ['sequence_id' => $sequence->id, 'lead_id' => $lead->id],
                [
                    'current_step' => 0,
                    'status' => 'active',
                    'next_send_at' => now()->addHours($firstStep->delay_hours),
                ]
            );

            if ($enrollment->wasRecentlyCreated) {
                ProcessSequenceStepJob::dispatch($enrollment)
                    ->delay(now()->addHours($firstStep->delay_hours));
            }
        }
    }

    public function capturar(Loja $loja, array $data): Lead
    {
        $distancia = null;
        $isNearby = false;
        $score = 30;

        if (isset($data['latitude'], $data['longitude'])) {
            $distancia = $this->geoService->calcularDistancia(
                $loja,
                (float) $data['latitude'],
                (float) $data['longitude']
            );
            $isNearby = $this->geoService->isNearby($loja, (float) $data['latitude'], (float) $data['longitude']);
            $score = $this->geoService->calcularLeadScore($distancia, $loja->raio_atendimento);
        }

        $lead = Lead::updateOrCreate(
            ['loja_id' => $loja->id, 'telefone' => $data['telefone']],
            [
                'nome' => $data['nome'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'cidade' => $data['cidade'] ?? null,
                'distancia_km' => $distancia,
                'is_nearby' => $isNearby,
                'lead_score' => $score,
            ]
        );

        FollowUpWhatsAppJob::dispatch($lead)->delay(now()->addHours(24));

        $this->enrollInSequences($loja, $lead);

        return $lead;
    }
}
