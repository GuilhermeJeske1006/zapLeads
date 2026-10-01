<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message to a prospect, reviewed before it goes out: the first one (etapa 0, written in three
 * angles to pick from) or a follow-up of the cold cadence (etapa 1..n).
 *
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
        'etapa',
        'dor_hipotese',
        'sequence_enrollment_id',
    ];

    protected $attributes = [
        'etapa' => 0,
    ];

    protected $casts = [
        'etapa'         => 'integer',
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

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(SequenceEnrollment::class, 'sequence_enrollment_id');
    }

    public function isFollowUp(): bool
    {
        return $this->etapa > 0;
    }

    /** @return array{angulo: string, mensagem: string, gancho_usado: ?string}|null */
    public function variante(string $angulo): ?array
    {
        return collect($this->variantes ?? [])->firstWhere('angulo', $angulo);
    }
}
