<?php

namespace App\Services\Prospecting;

use Illuminate\Support\Str;

/**
 * Rules an AI-written prospect message must pass before it reaches the review queue: short, no
 * links, at most one emoji, none of the worn-out phrases, no shouting, a question to answer, and no
 * number the model wasn't given (an invented "30% more clients" is a lie told in the user's name).
 */
final class OutreachMessageValidator
{
    public const MAX_LENGTH = 300;

    /** Lowercase and without accents, as normalize() leaves the text. */
    private const FORBIDDEN = [
        'espero que esteja bem', 'espero que voce esteja bem', 'meu nome e', 'gostaria de apresentar',
        'solucao inovadora', 'parceria',
        'espero que estes bien', 'mi nombre es', 'me gustaria presentar', 'solucion innovadora',
    ];

    private const LINK = '/(https?:\/\/|www\.|wa\.me|\b[a-z0-9-]+\.(com|net|org|io|app|me|br|ar)\b)/iu';

    private const EMOJI = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}]/u';

    /** A run of 4+ capital letters, like "GRÁTIS" or "PROMOÇÃO". */
    private const CAPS = '/\b\p{Lu}{4,}\b/u';

    private const NUMBER = '/\d+(?:[.,]\d+)?/u';

    /**
     * Short codes for what breaks the rules (e.g. "too_long", "number:30"); empty when it passes.
     *
     * @param  string  $sources  everything the model was given: the facts it may quote
     * @return list<string>
     */
    public static function violations(string $message, string $sources, bool $needsQuestion = true): array
    {
        $violations = [];
        $normalized = self::normalize($message);

        if (mb_strlen($message) > self::MAX_LENGTH) {
            $violations[] = 'too_long';
        }

        if (preg_match(self::LINK, $message)) {
            $violations[] = 'link';
        }

        if (preg_match_all(self::EMOJI, $message) > 1) {
            $violations[] = 'emojis';
        }

        foreach (self::FORBIDDEN as $phrase) {
            if (preg_match('/\b' . preg_quote($phrase, '/') . '\b/u', $normalized)) {
                $violations[] = "phrase:{$phrase}";
            }
        }

        // Names in the data may be written in capitals ("CNPJ", "STUDIO BELLA"): those are allowed.
        preg_match_all(self::CAPS, $message, $caps);
        if (array_filter($caps[0], fn (string $word) => !str_contains($sources, $word))) {
            $violations[] = 'caps';
        }

        $known = self::numbers($sources);
        foreach (array_unique(self::numbers($message)) as $number) {
            if (!in_array($number, $known, true)) {
                $violations[] = "number:{$number}";
            }
        }

        if ($needsQuestion && !str_contains($message, '?')) {
            $violations[] = 'no_question';
        }

        return $violations;
    }

    /** "4,8" and "4.8" are the same number. @return list<string> */
    private static function numbers(string $text): array
    {
        preg_match_all(self::NUMBER, $text, $matches);

        return array_map(fn (string $n) => str_replace(',', '.', $n), $matches[0]);
    }

    private static function normalize(string $text): string
    {
        return mb_strtolower(Str::ascii($text));
    }
}
