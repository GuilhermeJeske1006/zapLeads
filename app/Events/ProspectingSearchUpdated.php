<?php

namespace App\Events;

use App\Models\ProspectingSearch;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A search moved on (stage, counts, a lead's contacts found). Sent right away, not queued: the job
 * that sends it is the one the user is waiting for, and a queued broadcast would wait behind it.
 */
class ProspectingSearchUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public ProspectingSearch $search,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("empresa.{$this->search->empresa_id}.prospecting")];
    }

    public function broadcastAs(): string
    {
        return 'search.updated';
    }

    /** Just enough to know which search changed; the screen reads the rest from the database. */
    public function broadcastWith(): array
    {
        return [
            'id'     => $this->search->id,
            'status' => $this->search->status,
            'stage'  => $this->search->stage,
        ];
    }
}
