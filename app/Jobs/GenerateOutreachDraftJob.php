<?php

namespace App\Jobs;

use App\Models\OutreachDraft;
use App\Services\Prospecting\OutreachService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/** Writes a prospect's first message off the request, so the screen doesn't wait on the AI. */
class GenerateOutreachDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public readonly int $draftId,
    ) {}

    public function handle(OutreachService $outreach): void
    {
        $draft = OutreachDraft::find($this->draftId);

        if ($draft?->status === 'generating') {
            $outreach->prepare($draft);
        }
    }

    public function failed(?Throwable $e): void
    {
        OutreachDraft::whereKey($this->draftId)
            ->where('status', 'generating')
            ->update(['status' => 'failed', 'erro' => 'generation_failed']);
    }
}
