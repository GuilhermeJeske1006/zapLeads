<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One way to reach a lead, with where it was found (origem/evidencia) and how likely it is to be
 * the WhatsApp of someone who decides (confianca 0-100).
 */
class LeadContact extends Model
{
    /** Below this, the lead has no probable WhatsApp: call, or try from the user's own phone. */
    public const MIN_WHATSAPP_CONFIDENCE = 40;

    protected $fillable = [
        'lead_id',
        'empresa_id',
        'tipo',
        'valor',
        'valor_e164',
        'line_type',
        'origem',
        'confianca',
        'provavel_decisor',
        'is_primary',
        'evidencia',
        'verificado_em',
    ];

    protected $casts = [
        'confianca'        => 'integer',
        'provavel_decisor' => 'boolean',
        'is_primary'       => 'boolean',
        'verificado_em'    => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function isPhone(): bool
    {
        return in_array($this->tipo, ['whatsapp', 'telefone'], true);
    }
}
