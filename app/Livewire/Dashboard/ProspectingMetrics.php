<?php

namespace App\Livewire\Dashboard;

use App\Models\Empresa;
use App\Services\Metrics\FunnelReport;
use App\Services\Prospecting\AngleExperiment;
use Livewire\Component;

/**
 * Dashboard: the prospecting funnel (by period or by search) and the A/B test of the first message
 * (by angle, template and how it was sent).
 */
class ProspectingMetrics extends Component
{
    public const PERIODS = ['7', '30', '90', 'all'];

    public Empresa $empresa;

    public string $periodo = '30';

    /** '' = every search of the period. */
    public string $buscaId = '';

    public function updatedPeriodo(): void
    {
        if (!in_array($this->periodo, self::PERIODS, true)) {
            $this->periodo = '30';
        }
    }

    public function render(FunnelReport $report, AngleExperiment $experiment)
    {
        $searches = $this->empresa->prospectingSearches()
            ->where('status', 'done')
            ->latest('id')
            ->limit(20)
            ->get(['id', 'tipo_cliente', 'local_label', 'results_count', 'created_at']);

        // Only one of the empresa's own searches filters the funnel.
        $search = $this->buscaId !== '' ? $searches->firstWhere('id', (int) $this->buscaId) : null;

        $angles = $experiment->stats($this->empresa->id);

        return view('livewire.dashboard.prospecting-metrics', [
            'funil'     => $report->funnel($this->empresa, $this->periodo === 'all' ? null : (int) $this->periodo, $search?->id),
            'searches'  => $searches,
            'angles'    => $angles,
            'winner'    => $experiment->winner($angles),
            'templates' => $report->byTemplate($this->empresa->id),
            'canais'    => $report->byChannel($this->empresa->id),
            'timezone'  => $this->empresa->timezone ?: config('app.timezone'),
        ]);
    }
}
