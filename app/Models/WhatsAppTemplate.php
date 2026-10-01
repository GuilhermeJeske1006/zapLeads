<?php

namespace App\Models;

use App\Services\Prospecting\MessageParts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Twilio Content template approved by Meta. Business-initiated messages outside the 24h session
 * can only be sent as one of these.
 */
class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    /** What a placeholder can be filled with: field => label. */
    public const FIELDS = [
        'lead_nome'             => 'messages.template_field_lead_nome',
        'lead_cidade'           => 'messages.template_field_lead_cidade',
        'empresa_nome'          => 'messages.template_field_empresa_nome',
        'contato_nome'          => 'messages.template_field_contato_nome',
        'mensagem'              => 'messages.template_field_mensagem',
        'mensagem_sem_saudacao' => 'messages.template_field_mensagem_sem_saudacao',
        'abertura'              => 'messages.template_field_abertura',
        'pergunta'              => 'messages.template_field_pergunta',
    ];

    protected $fillable = [
        'empresa_id',
        'nome',
        'content_sid',
        'categoria',
        'idioma',
        'corpo_preview',
        'variaveis',
        'status',
        'ativo',
        'synced_at',
    ];

    protected $casts = [
        'variaveis' => 'array',
        'ativo'     => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopeUsable(Builder $query): void
    {
        $query->where('status', 'approved')->where('ativo', true);
    }

    /**
     * Placeholder values ({"1": "Studio Bella", ...}), or null when one is unmapped or comes out
     * empty: WhatsApp rejects empty parameters, and line breaks aren't allowed in them. The parts of
     * the message (opening, question) are cut from the reviewed text.
     *
     * @return array<string, string>|null
     */
    public function variablesFor(Lead $lead, string $mensagem): ?array
    {
        $parts = MessageParts::split($mensagem);
        $values = [
            'lead_nome'             => $lead->nome,
            'lead_cidade'           => $lead->cidade,
            'empresa_nome'          => $this->empresa?->nome,
            'contato_nome'          => $lead->primeiroNomeDecisor(),
            'mensagem'              => $mensagem,
            'mensagem_sem_saudacao' => MessageParts::withoutGreeting($mensagem),
            'abertura'              => $parts['abertura'],
            'pergunta'              => $parts['pergunta'],
        ];

        $variables = [];
        foreach ($this->variaveis ?? [] as $key => $field) {
            $value = trim((string) preg_replace('/\s+/u', ' ', (string) ($values[$field] ?? '')));
            if ($value === '') {
                return null;
            }
            $variables[(string) $key] = $value;
        }

        return $variables;
    }

    /** The body as the lead will read it. */
    public function render(array $variables): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $m) => $variables[$m[1]] ?? $m[0],
            (string) $this->corpo_preview,
        );
    }
}
