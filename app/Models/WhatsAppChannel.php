<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppChannel extends Model
{
    protected $table = 'whatsapp_channels';

    protected $fillable = [
        'empresa_id', 'nome', 'numero', 'is_default', 'ativo', 'limite_diario_prospeccao',
        'qualidade', 'limite_mensagens', 'sender_status', 'saude_verificada_em',
        'prospeccao_pausada_em', 'pausa_motivo', 'prospeccao_retomada_em',
    ];

    protected $casts = [
        'is_default'               => 'boolean',
        'ativo'                    => 'boolean',
        'limite_diario_prospeccao' => 'integer',
        'saude_verificada_em'      => 'datetime',
        'prospeccao_pausada_em'    => 'datetime',
        'prospeccao_retomada_em'   => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** No cold outreach leaves this number until the user resumes it (ChannelHealthService). */
    public function isProspectingPaused(): bool
    {
        return $this->prospeccao_pausada_em !== null;
    }
}
