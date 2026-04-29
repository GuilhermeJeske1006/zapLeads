<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectingSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'empresa_id',
        'descricao_empresa',
        'tipo_cliente',
        'latitude',
        'longitude',
        'radius_km',
        'keywords',
        'status',
        'results_count',
        'error',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_km' => 'float',
        'keywords' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'prospecting_search_id');
    }
}
