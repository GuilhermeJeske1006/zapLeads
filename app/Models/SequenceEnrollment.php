<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequenceEnrollment extends Model
{
    protected $fillable = [
        'sequence_id',
        'lead_id',
        'current_step',
        'status',
        'next_send_at',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'next_send_at' => 'datetime',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
