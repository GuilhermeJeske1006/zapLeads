<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\WhatsAppChannel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'empresa_id', 'whatsapp_channel_id', 'lead_id', 'telefone', 'telefone_e164', 'nome_contato',
        'last_message', 'last_message_at', 'unread_count', 'status',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Conversation $conversation) {
            if ($conversation->isDirty('telefone') && !$conversation->isDirty('telefone_e164')) {
                $conversation->telefone_e164 = Phone::canonical($conversation->telefone, $conversation->empresa?->country ?? 'BR');
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function whatsappChannel(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChannel::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->nome_contato ?? $this->telefone;
    }
}
