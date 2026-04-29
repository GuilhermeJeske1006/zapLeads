<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'empresa_id', 'nome', 'mensagem', 'imagem', 'status',
        'filtros', 'total_enviados', 'total_erros',
        'scheduled_at', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'filtros' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'campaign_leads')
            ->withPivot('status', 'sent_at')
            ->withTimestamps();
    }
}
