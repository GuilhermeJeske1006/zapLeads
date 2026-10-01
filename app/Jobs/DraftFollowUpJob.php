<?php

namespace App\Jobs;

use App\Models\SequenceEnrollment;
use App\Services\Prospecting\FollowUpCadence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** A step of the cold cadence is due: its follow-up goes to the review queue (if the cadence still runs). */
class DraftFollowUpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $enrollmentId,
        public readonly int $step,
    ) {}

    public function handle(FollowUpCadence $cadence): void
    {
        if ($enrollment = SequenceEnrollment::find($this->enrollmentId)) {
            $cadence->due($enrollment, $this->step);
        }
    }
}
