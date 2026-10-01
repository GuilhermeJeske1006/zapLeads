<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\Scoring\LeadScoringService;
use Illuminate\Console\Command;

class ScoreLeads extends Command
{
    protected $signature = 'leads:score {--empresa= : Only the leads of this empresa id}';

    protected $description = 'Recompute lead_score of prospect leads from what they already have (fit, contact, pain, distance), without calling the AI';

    public function handle(LeadScoringService $scoring): int
    {
        $count = 0;

        Lead::query()
            ->whereIn('source', LeadScoringService::SOURCES)
            ->when($this->option('empresa'), fn ($q, $id) => $q->where('empresa_id', (int) $id))
            ->with(['empresa', 'prospectingSearch:id,radius_km'])
            ->chunkById(200, function ($leads) use ($scoring, &$count) {
                foreach ($leads as $lead) {
                    $scoring->score($lead);
                    $lead->save();
                    $count++;
                }
            });

        $this->info("Leads scored: {$count}.");

        return self::SUCCESS;
    }
}
