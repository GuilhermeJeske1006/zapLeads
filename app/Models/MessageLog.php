<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'empresa_id', 'telefone', 'mensagem', 'tipo', 'direcao', 'status', 'provider_response',
    ];

    protected $casts = [
        'provider_response' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
