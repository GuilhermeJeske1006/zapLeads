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
        'whatsapp_channel_id',
        'whatsapp_template_id',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(OutreachDraft::class, 'outreach_draft_id');
    }
}
