<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** The contact enrichment picked as the lead's WhatsApp (its value is leads.telefone). */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(LeadContact::class)->where('is_primary', true);
    }

    /** Enriched and no contact reached the minimum: only a call or the user's own WhatsApp. */
    public function lacksProbableWhatsApp(): bool
    {
        return $this->enrichment_status === 'done'
            && $this->contact_confidence !== null
            && $this->contact_confidence < LeadContact::MIN_WHATSAPP_CONFIDENCE;
    }

    /** How well the lead matches the ideal customer (AI, 0-100); lead_score also weighs contact, pain and distance. */
    public function fitScore(): int
    {
        return (int) ($this->ai_insights['match_score'] ?? 0);
    }

    public function outreachAttempts(): HasMany
    {
        return $this->hasMany(OutreachAttempt::class);
    }

    /** How sure we are that decisor_nome runs the business: registry data 80, a web page 50. */
    public function decisorConfianca(): ?int
    {
        if (trim((string) $this->decisor_nome) === '') {
            return null;
        }

        return isset($this->dossie['decisor_fonte']) ? 50 : 80;
    }

    /** First name to greet the decision maker by, only when we trust it (60+). */
    public function primeiroNomeDecisor(): ?string
    {
        if (($this->decisorConfianca() ?? 0) < 60) {
            return null;
        }

        return explode(' ', trim($this->decisor_nome))[0];
    }

    /** The sales funnel, in order. Labels live in lang (messages.status_*). */
    public const STATUSES = [
        'novo'       => ['color' => 'gray'],
        'abordado'   => ['color' => 'blue'],
        'respondeu'  => ['color' => 'yellow'],
        'reuniao'    => ['color' => 'violet'],
        'proposta'   => ['color' => 'sky'],
        'convertido' => ['color' => 'green'],
        'descartado' => ['color' => 'red'],
    ];

    public static function statusLabel(?string $status): string
    {
        return __('messages.status_' . (array_key_exists((string) $status, self::STATUSES) ? $status : 'novo'));
    }

    /** Badge/select classes for a status. */
    public static function statusClasses(?string $status): string
    {
        return match (self::STATUSES[$status]['color'] ?? 'gray') {
            'blue'   => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
            'yellow' => 'bg-yellow-500/15 text-yellow-300 border-yellow-500/30',
            'violet' => 'bg-violet-500/15 text-violet-300 border-violet-500/30',
            'sky'    => 'bg-sky-500/15 text-sky-300 border-sky-500/30',
            'green'  => 'bg-green-500/15 text-green-300 border-green-500/30',
            'red'    => 'bg-red-500/15 text-red-300 border-red-500/30',
            default  => 'bg-gray-700/50 text-gray-300 border-gray-600',
        };
    }

    /** A message reached the lead (API, the user's WhatsApp, a sequence): "novo" moves to "abordado". */
    public function markApproached(): void
    {
        if (($this->status ?? 'novo') === 'novo') {
            $this->update(['status' => 'abordado']);
        }
    }

    /** The lead wrote back: early funnel statuses move to "respondeu", later ones are kept. */
    public function markReplied(): void
    {
        if (in_array($this->status ?? 'novo', ['novo', 'abordado'], true)) {
            $this->update(['status' => 'respondeu']);
        }
    }

    public function getScoreLabelAttribute(): string
    {
        return match (true) {
            $this->lead_score >= 80 => 'hot',
            $this->lead_score >= 50 => 'warm',
            default => 'cold',
        };
    }
}
