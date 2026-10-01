<?php

namespace App\Services\Prospecting;

/**
 * A prospect message cut into what a WhatsApp template can carry: the greeting, the opening (hook
 * or pain) and the question with whatever follows it. Cut from the final text, so it still holds
 * after the user edits the message.
 *
 *   "Oi, Carla! Vi que vocês têm nota 4,8. Seria absurdo eu te mostrar como? Se não fizer sentido, me avisa."
 *    saudacao   abertura                   pergunta (from the last question to the end)
 */
final class MessageParts
{
    private const GREETING = '/^(oi|olá|ola|opa|e aí|e ai|bom dia|boa tarde|boa noite|hola|buen día|buen dia|buenas|qué tal|que tal)\b/iu';

    /** A first sentence longer than this is already the message, not a greeting. */
    private const GREETING_MAX_WORDS = 5;

    /** @return array{saudacao: string, abertura: string, pergunta: string} */
    public static function split(string $text): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $sentences = preg_split('/(?<=[.!?…])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $saudacao = '';
        if ($sentences && preg_match(self::GREETING, $sentences[0]) && count(explode(' ', $sentences[0])) <= self::GREETING_MAX_WORDS) {
            $saudacao = array_shift($sentences);
        }

        $question = null;
        foreach ($sentences as $i => $sentence) {
            if (str_ends_with($sentence, '?')) {
                $question = $i;
            }
        }

        return [
            'saudacao' => $saudacao,
            'abertura' => implode(' ', array_slice($sentences, 0, $question ?? count($sentences))),
            'pergunta' => $question === null ? '' : implode(' ', array_slice($sentences, $question)),
        ];
    }

    /** The message as the lead reads it after the template's own greeting. */
    public static function withoutGreeting(string $text): string
    {
        $parts = self::split($text);

        return trim($parts['abertura'] . ' ' . $parts['pergunta']);
    }
}
