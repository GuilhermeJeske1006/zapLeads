<?php

namespace App\Jobs;

use App\Models\OutreachDraft;
use App\Services\Prospecting\OutreachException;
use App\Services\Prospecting\OutreachService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Sends an approved draft at its scheduled slot, unless it was skipped in the meantime. */
class SendOutreachDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $draftId,
    ) {}

    public function handle(OutreachService $outreach): void
    {
        $draft = OutreachDraft::find($this->draftId);

        if ($draft?->status !== 'approved') {
            return;
        }

        try {
            $outreach->send($draft);
        } catch (OutreachException $e) {
            $draft->update(['status' => 'failed', 'erro' => $e->reason]);
        }
    }
}
