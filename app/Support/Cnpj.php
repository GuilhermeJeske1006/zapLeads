<?php

namespace App\Support;

/** Brazilian company registry number (14 digits, two check digits). */
final class Cnpj
{
    /** The 14 digits when $raw is a valid CNPJ, otherwise null. */
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);

        return self::isValid($digits) ? $digits : null;
    }

    public static function isValid(string $digits): bool
    {
        if (!preg_match('/^\d{14}$/', $digits) || preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        foreach ([12, 13] as $length) {
            $weights = $length === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            foreach ($weights as $i => $weight) {
                $sum += (int) $digits[$i] * $weight;
            }
            $check = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

            if ((int) $digits[$length] !== $check) {
                return false;
            }
        }

        return true;
    }

    /** Valid CNPJs written in $text, formatted or not, in order of appearance. */
    public static function findAll(string $text): array
    {
        preg_match_all('/(?<!\d)\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}(?!\d)/', $text, $matches);

        return array_values(array_unique(array_filter(array_map([self::class, 'normalize'], $matches[0]))));
    }
}
