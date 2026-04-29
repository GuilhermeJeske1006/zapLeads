<?php

namespace App\Services;

use App\Jobs\FollowUpWhatsAppJob;
use App\Jobs\ProcessSequenceStepJob;
use App\Models\Lead;
use App\Models\Empresa;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;

class LeadService
{
    public function __construct(
        private GeoService $geoService,
    ) {}

    private function enrollInSequences(Empresa $empresa, Lead $lead): void
    {
        $sequences = Sequence::where('empresa_id', $empresa->id)
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

    public function capturar(Empresa $empresa, array $data): Lead
    {
        $distancia = null;
        $isNearby = false;
        $score = 30;

        if (isset($data['latitude'], $data['longitude'])) {
            $distancia = $this->geoService->calcularDistancia(
                $empresa,
                (float) $data['latitude'],
                (float) $data['longitude']
            );
            $isNearby = $this->geoService->isNearby($empresa, (float) $data['latitude'], (float) $data['longitude']);
            $score = $this->geoService->calcularLeadScore($distancia, $empresa->raio_atendimento);
        }

        $lead = Lead::updateOrCreate(
            ['empresa_id' => $empresa->id, 'telefone' => $data['telefone']],
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

        $this->enrollInSequences($empresa, $lead);

        return $lead;
    }
}
