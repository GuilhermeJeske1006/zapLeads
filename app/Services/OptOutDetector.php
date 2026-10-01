<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Whether an inbound message asks to stop receiving messages. Terms match whole words only, so
 * "pare" doesn't match "parece" nor "parar" match "preparar". A bare command ("Sair", "pode parar")
 * is enough; in a longer message the term may mean something else ("não quero perder essa
 * promoção"), so the fast model decides. When it can't, the request is honoured: messaging
 * someone who asked to stop is the worse mistake.
 */
class OptOutDetector
{
    private const TERMS = [
        'sair', 'parar', 'pare', 'stop', 'cancelar', 'remover', 'remova', 'remove',
        'descadastrar', 'descadastre', 'nao quero', 'nao tenho interesse', 'nao me mande',
        'nao mande mais', 'nao envie mais',
    ];

    /** Up to this many words, a message with a term is the command itself. */
    private const COMMAND_WORDS = 2;

    public function __construct(
        private readonly AIService $ai,
    ) {}

    public function isOptOut(string $text): bool
    {
        $normalized = self::normalize($text);

        if (!preg_match('/\b(' . implode('|', self::TERMS) . ')\b/', $normalized)) {
            return false;
        }

        if (count(explode(' ', $normalized)) <= self::COMMAND_WORDS) {
            return true;
        }

        return ($this->ai->classificarRespostaProspeccao($text) ?? 'opt_out') === 'opt_out';
    }

    /** Lowercase, no accents or punctuation, single spaces. */
    private static function normalize(string $text): string
    {
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text)));

        return trim($text);
    }
}
