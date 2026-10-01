<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * Phone numbers as canonical E.164 ("+5547999998888"), the only format used to compare,
 * deduplicate and send. The empresa's country decides how national numbers are read.
 */
class Phone
{
    /** Valid E.164 number, or null when the input can't be read as a valid number. */
    public static function normalize(?string $raw, string $defaultRegion = 'BR'): ?string
    {
        [$raw, $digits] = self::clean($raw);
        if ($digits === '') {
            return null;
        }

        // Without "+" the number is read as national first; failing that it may still carry
        // its country code (e.g. "14155238886" saved from an old webhook).
        $candidates = str_starts_with($raw, '+') ? ['+' . $digits] : [$digits, '+' . $digits];

        foreach ($candidates as $candidate) {
            $number = self::parse($candidate, $defaultRegion);
            if ($number !== null) {
                return self::util()->format($number, PhoneNumberFormat::E164);
            }
        }

        return null;
    }

    /**
     * normalize(), but an explicit international number ("+...") that libphonenumber can't
     * validate is kept as "+digits": an inbound sender must never be dropped.
     */
    public static function canonical(?string $raw, string $defaultRegion = 'BR'): ?string
    {
        $normalized = self::normalize($raw, $defaultRegion);
        if ($normalized !== null) {
            return $normalized;
        }

        [$raw, $digits] = self::clean($raw);

        return str_starts_with($raw, '+') && $digits !== '' ? '+' . $digits : null;
    }

    /** @return 'mobile'|'fixed'|'fixed_or_mobile'|'toll_free'|'voip'|'unknown' */
    public static function lineType(string $e164): string
    {
        $number = self::parse($e164, 'ZZ');

        return match ($number ? self::util()->getNumberType($number) : null) {
            PhoneNumberType::MOBILE               => 'mobile',
            PhoneNumberType::FIXED_LINE           => 'fixed',
            PhoneNumberType::FIXED_LINE_OR_MOBILE => 'fixed_or_mobile',
            PhoneNumberType::TOLL_FREE            => 'toll_free',
            PhoneNumberType::VOIP                 => 'voip',
            default                               => 'unknown',
        };
    }

    public static function isLikelyWhatsApp(string $e164): bool
    {
        return in_array(self::lineType($e164), ['mobile', 'fixed_or_mobile'], true);
    }

    /** Opens a chat in the user's own WhatsApp, with $text ready to send. */
    public static function waMeLink(?string $raw, string $defaultRegion = 'BR', ?string $text = null): ?string
    {
        $e164 = self::canonical($raw, $defaultRegion);
        if (!$e164) {
            return null;
        }

        $link = 'https://wa.me/' . ltrim($e164, '+');

        return ($text ?? '') !== '' ? $link . '?text=' . rawurlencode($text) : $link;
    }

    private static function parse(string $candidate, string $region): ?PhoneNumber
    {
        try {
            $number = self::util()->parse($candidate, strtoupper($region));
        } catch (NumberParseException) {
            return null;
        }

        return self::util()->isValidNumber($number) ? $number : self::withBrazilianNinthDigit($number);
    }

    /**
     * WhatsApp still reports many Brazilian mobiles in the old 8-digit form (+55 47 9280-1006);
     * the dialable number has a 9 after the area code. Only applied to otherwise invalid numbers.
     */
    private static function withBrazilianNinthDigit(PhoneNumber $number): ?PhoneNumber
    {
        $national = (string) $number->getNationalNumber();

        if ($number->getCountryCode() !== 55 || strlen($national) !== 10 || $national[2] < '6') {
            return null;
        }

        $withNine = (clone $number)->setNationalNumber(substr($national, 0, 2) . '9' . substr($national, 2));

        return self::util()->getNumberType($withNine) === PhoneNumberType::MOBILE ? $withNine : null;
    }

    /** @return array{string, string} the trimmed input without "whatsapp:", and its digits */
    private static function clean(?string $raw): array
    {
        $raw = preg_replace('/^whatsapp:/i', '', trim((string) $raw));

        return [$raw, preg_replace('/\D/', '', $raw)];
    }

    private static function util(): PhoneNumberUtil
    {
        return PhoneNumberUtil::getInstance();
    }
}
