<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'empresa_id',
        'prospecting_search_id',
        'nome',
        'telefone',
        'telefone_e164',
        'source',
        'external_source',
        'external_id',
        'latitude',
        'longitude',
        'cidade',
        'endereco',
        'website',
        'distancia_km',
        'is_nearby',
        'lead_score',
        'status',
        'opted_out_at',
        'ai_insights',
        'cnpj',
        'razao_social',
        'decisor_nome',
        'decisor_cargo',
        'porte',
        'data_abertura',
        'situacao_cadastral',
        'instagram',
        'email',
        'business_status',
        'horario_funcionamento',
        'enrichment_status',
        'enriched_at',
        'contact_confidence',
        'dossie',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'distancia_km' => 'float',
        'is_nearby' => 'boolean',
        'opted_out_at' => 'datetime',
        'ai_insights' => 'array',
        'data_abertura' => 'date',
        'horario_funcionamento' => 'array',
        'enriched_at' => 'datetime',
        'contact_confidence' => 'integer',
        'dossie' => 'array',
    ];

    protected static function booted(): void
    {
        // telefone keeps what the user/source typed; telefone_e164 is what we match and send to.
        static::saving(function (Lead $lead) {
            if ($lead->isDirty('telefone') && !$lead->isDirty('telefone_e164')) {
                $lead->telefone_e164 = Phone::canonical($lead->telefone, $lead->empresa?->country ?? 'BR');
            }
        });
    }

    public function isOptedOut(): bool
    {
        return $this->opted_out_at !== null;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function prospectingSearch(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearch::class, 'prospecting_search_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(LeadContact::class);
    }

    /** Enriched and no contact reached the minimum: only a call or the user's own WhatsApp. */
    public function lacksProbableWhatsApp(): bool
    {
        return $this->enrichment_status === 'done'
            && $this->contact_confidence !== null
            && $this->contact_confidence < LeadContact::MIN_WHATSAPP_CONFIDENCE;
    }

    /** The AI match score from the search, until scoring v2 replaces lead_score. */
    public function fitScore(): int
    {
        return (int) ($this->ai_insights['match_score'] ?? $this->lead_score ?? 0);
    }

    public function outreachAttempts(): HasMany
    {
        return $this->hasMany(OutreachAttempt::class);
    }

    public const STATUSES = [
        'novo'        => ['label' => 'Novo',        'color' => 'gray'],
        'contatado'   => ['label' => 'Contatado',   'color' => 'blue'],
        'interessado' => ['label' => 'Interessado', 'color' => 'yellow'],
        'convertido'  => ['label' => 'Convertido',  'color' => 'green'],
        'descartado'  => ['label' => 'Descartado',  'color' => 'red'],
    ];

    public function getScoreLabelAttribute(): string
    {
        return match (true) {
            $this->lead_score >= 80 => 'hot',
            $this->lead_score >= 50 => 'warm',
            default => 'cold',
        };
    }
}
