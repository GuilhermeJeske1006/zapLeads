<?php

namespace App\Jobs;

use App\Models\ProspectingSearch;
use App\Services\Prospecting\ProspectingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class FindInternetLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 180;

    public function __construct(
        public readonly int $searchId,
        public readonly int $maxResults = 60,
        public readonly ?float $customLat = null,
        public readonly ?float $customLng = null,
        public readonly string $customLocationLabel = '',
    ) {}

    public function handle(ProspectingService $prospecting): void
    {
        $search = ProspectingSearch::find($this->searchId);
        if (!$search) {
            return;
        }

        $prospecting->run(
            $search,
            $this->maxResults,
            $this->customLat,
            $this->customLng,
            $this->customLocationLabel,
        );
    }

    /** Without this a crashed job leaves the search "running" and the UI polling forever. */
    public function failed(Throwable $e): void
    {
        $search = ProspectingSearch::find($this->searchId);

        $search?->update([
            'status' => 'failed',
            'error'  => 'messages.search_failed_generic',
        ]);
        $search?->broadcastProgress();
    }
}
