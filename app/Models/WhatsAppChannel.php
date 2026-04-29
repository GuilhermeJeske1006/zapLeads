<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppChannel extends Model
{
    protected $table = 'whatsapp_channels';

    protected $fillable = ['empresa_id', 'nome', 'numero', 'is_default', 'ativo'];

    protected $casts = [
        'is_default' => 'boolean',
        'ativo'      => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
