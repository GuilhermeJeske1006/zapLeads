<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** First message to a prospect, written before it is sent. */
class OutreachDraft extends Model
{
    protected $fillable = [
        'empresa_id',
        'lead_id',
        'whatsapp_channel_id',
        'texto_final',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
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
