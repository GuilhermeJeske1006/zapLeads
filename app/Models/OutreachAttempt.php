<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message that reached the prospect (etapa 0 = first, then follow-ups), through the API or the user's own WhatsApp ("assisted"). */
class OutreachAttempt extends Model
{
    protected $fillable = [
        'empresa_id',
        'lead_id',
        'outreach_draft_id',
        'canal',
        'mensagem',
        'variante',
        'etapa',
        'responded_at',
    ];

    protected $casts = [
        'etapa'        => 'integer',
        'responded_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(OutreachDraft::class, 'outreach_draft_id');
    }
}
