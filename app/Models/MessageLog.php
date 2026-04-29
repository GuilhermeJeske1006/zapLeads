<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'loja_id', 'telefone', 'mensagem', 'tipo', 'direcao', 'status', 'zapi_response',
    ];

    protected $casts = [
        'zapi_response' => 'array',
    ];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }
}
