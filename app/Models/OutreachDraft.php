<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * First message to a prospect, reviewed before it goes out:
 * generating → draft → approved (scheduled on the channel) → sent, or skipped / failed.
 */
class OutreachDraft extends Model
{
    /** Still in the review queue: a lead gets one of these at a time. */
    public const PENDING = ['generating', 'draft', 'approved'];

    protected $fillable = [
        'empresa_id',
        'lead_id',
        'whatsapp_channel_id',
        'variantes',
        'variante_escolhida',
        'texto_final',
        'status',
        'erro',
        'scheduled_for',
        'sent_at',
    ];

    protected $casts = [
        'variantes'     => 'array',
        'scheduled_for' => 'datetime',
        'sent_at'       => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function whatsappChannel(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChannel::class);
    }
}
