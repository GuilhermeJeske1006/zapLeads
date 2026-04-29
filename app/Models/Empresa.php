<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\WhatsAppChannel;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nome',
        'whatsapp',
        'endereco',
        'cidade',
        'latitude',
        'longitude',
        'raio_atendimento',
        'slug',
        'logo',
        'ativo',
        'descricao_empresa',
        'tipo_cliente_alvo',
        'bot_ativo',
        'bot_horario_inicio',
        'bot_horario_fim',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(Sequence::class);
    }

    public function whatsappChannels(): HasMany
    {
        return $this->hasMany(WhatsAppChannel::class);
    }

    public function defaultChannel(): ?WhatsAppChannel
    {
        return $this->whatsappChannels()
            ->where('ativo', true)
            ->orderByDesc('is_default')
            ->first();
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo
            ? asset('storage/' . $this->logo)
            : asset('images/default-store.png');
    }
}
