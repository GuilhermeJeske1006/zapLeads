<?php

namespace App\Jobs;

use App\Models\Empresa;
use App\Services\Costs\UsageMeter;
use App\Services\Scoring\LeadScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Re-evaluates a handful of leads with the AI ("Recalcular scores"): one request per job. */
class ScoreLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;
    public int $backoff = 30;
    public int $timeout = 60;

    /** @param  list<int>  $leadIds */
    public function __construct(
        public readonly int $empresaId,
        public readonly array $leadIds,
    ) {}

    public function handle(LeadScoringService $scoring): void
    {
        $empresa = Empresa::find($this->empresaId);
        if (!$empresa) {
            return;
        }

        app(UsageMeter::class)->within(
            ['empresa_id' => $empresa->id, 'origem' => 'score'],
            fn () => $scoring->evaluate($empresa, $empresa->leads()->whereKey($this->leadIds)->get()),
        );
    }
}
