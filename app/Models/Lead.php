<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'distancia_km' => 'float',
        'is_nearby' => 'boolean',
        'opted_out_at' => 'datetime',
        'ai_insights' => 'array',
    ];

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

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_leads')
            ->withPivot('status', 'sent_at')
            ->withTimestamps();
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
