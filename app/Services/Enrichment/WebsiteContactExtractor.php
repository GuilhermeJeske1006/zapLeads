<?php

namespace App\Services\Enrichment;

use App\Support\Cnpj;
use App\Support\Net\SafeHttp;
use App\Support\Phone;

/** What a business publishes about itself on one HTML page: WhatsApp, phones, e-mails, social profiles, CNPJ. */
final class WebsiteContactExtractor
{
    private const NOT_PROFILES = [
        'instagram' => ['p', 'reel', 'reels', 'explore', 'stories', 'accounts', 'tv', 'direct', 'about', 'developer', 'legal'],
        'facebook'  => ['sharer', 'sharer.php', 'share', 'share.php', 'plugins', 'tr', 'dialog', 'login', 'groups', 'events', 'watch', 'help', 'policies', 'privacy'],
    ];

    /** Site builders and error trackers put their own addresses in the page; subdomains included. */
    private const IGNORED_EMAIL_DOMAINS = ['example.com', 'sentry.io', 'wixpress.com', 'domain.com', 'email.com'];

    /**
     * @return array{
     *   whatsapp: list<string>, whatsapp_text: list<string>, tel: list<string>, emails: list<string>,
     *   instagram: list<string>, facebook: list<string>, cnpjs: list<string>, links: list<string>, text: string
     * } whatsapp holds E.164 numbers; tel and whatsapp_text keep the number as written; text skips nav, header and footer
     */
    public static function extract(string $html, string $pageUrl, string $region): array
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        // Contacts and the CNPJ usually sit in the footer; the "about" text doesn't.
        $text = self::visibleText($html, withLayout: true);

        return [
            'whatsapp'      => self::whatsappLinks($decoded, $region),
            'whatsapp_text' => self::whatsappInText($text, $region),
            'tel'           => self::telLinks($decoded, $region),
            'emails'        => self::emails($decoded),
            'instagram'     => self::profiles($decoded, 'instagram'),
            'facebook'      => self::profiles($decoded, 'facebook'),
            'cnpjs'         => Cnpj::findAll($text),
            'links'         => self::sameSiteLinks($decoded, $pageUrl),
            'text'          => self::visibleText($html, withLayout: false),
        ];
    }

    /** "@handle" for an Instagram URL or handle, null when it isn't a profile. */
    public static function instagramHandle(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('#instagram\.com/([A-Za-z0-9_.]{2,30})#i', $value, $m)) {
            $value = $m[1];
        }

        $handle = ltrim($value, '@');
        if (!preg_match('/^[A-Za-z0-9_.]{2,30}$/', $handle) || in_array(strtolower($handle), self::NOT_PROFILES['instagram'], true)) {
            return null;
        }

        return '@' . strtolower($handle);
    }

    /** Text a visitor reads, without scripts and styles; without navigation, header and footer unless $withLayout. */
    public static function visibleText(string $html, bool $withLayout = true): string
    {
        $hidden = $withLayout ? 'script|style|noscript|svg' : 'script|style|noscript|svg|nav|header|footer';
        $html = preg_replace('#<(' . $hidden . ')\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = html_entity_decode(strip_tags(preg_replace('#<(br|/p|/div|/li|/h\d)\b[^>]*>#i', "\n", (string) $html)), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', preg_replace('/\s*\n\s*/', "\n", $text)));
    }

    /** @return list<string> E.164 numbers of wa.me / api.whatsapp.com / whatsapp:// links in $html (or a single URL) */
    public static function whatsappLinks(string $html, string $region): array
    {
        preg_match_all(
            '#(?:wa\.me/|(?:api|web)\.whatsapp\.com/send/?\?(?:[^"\'\s>]*?&)?phone=|whatsapp://send\?(?:[^"\'\s>]*?&)?phone=)(?:%2B|\+)?(\d{8,16})#i',
            $html,
            $matches,
        );

        $numbers = [];
        foreach ($matches[1] as $digits) {
            // Links should carry the country code; some sites leave it out.
            $e164 = Phone::normalize('+' . $digits) ?? Phone::normalize($digits, $region);
            if ($e164 !== null) {
                $numbers[] = $e164;
            }
        }

        return array_values(array_unique($numbers));
    }

    /** @return list<string> numbers written right after the word WhatsApp ("WhatsApp: (47) 99999-8888") */
    private static function whatsappInText(string $text, string $region): array
    {
        preg_match_all('/whats\s*app[^\d+\n]{0,30}(\+?\(?\d[\d\s().-]{7,18}\d)/iu', $text, $matches);

        return array_values(array_unique(array_filter(
            array_map('trim', $matches[1]),
            fn (string $raw) => Phone::normalize($raw, $region) !== null,
        )));
    }

    /** @return list<string> */
    private static function telLinks(string $html, string $region): array
    {
        preg_match_all('#href\s*=\s*["\']tel:([^"\']+)["\']#i', $html, $matches);

        return array_values(array_unique(array_filter(
            array_map(fn (string $raw) => trim(rawurldecode($raw)), $matches[1]),
            fn (string $raw) => Phone::normalize($raw, $region) !== null,
        )));
    }

    /** @return list<string> */
    private static function emails(string $html): array
    {
        preg_match_all('#mailto:([^"\'?\s>]+)#i', $html, $mailto);
        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', strip_tags($html), $inText);

        $emails = [];
        foreach ([...$mailto[1], ...$inText[0]] as $email) {
            $email = strtolower(trim(rawurldecode($email)));
            $domain = substr(strrchr($email, '@') ?: '', 1);

            $ignored = array_filter(self::IGNORED_EMAIL_DOMAINS, fn (string $d) => $domain === $d || str_ends_with($domain, ".{$d}"));

            if (filter_var($email, FILTER_VALIDATE_EMAIL) && !preg_match('/\.(png|jpe?g|gif|webp|svg)$/', $email) && $ignored === []) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /** @return list<string> "@handle" for Instagram, profile URL for Facebook */
    private static function profiles(string $html, string $network): array
    {
        $pattern = $network === 'instagram'
            ? '#instagram\.com/([A-Za-z0-9_.]{2,30})/?(?=["\'?\s<])#i'
            : '#facebook\.com/([A-Za-z0-9.\-]{3,80})/?(?=["\'?\s<])#i';

        preg_match_all($pattern, $html, $matches);

        $profiles = [];
        foreach ($matches[1] as $name) {
            if (in_array(strtolower($name), self::NOT_PROFILES[$network], true)) {
                continue;
            }
            $profiles[] = $network === 'instagram' ? '@' . strtolower($name) : 'https://www.facebook.com/' . $name;
        }

        return array_values(array_unique($profiles));
    }

    /** @return list<string> absolute links to pages of the same host */
    private static function sameSiteLinks(string $html, string $pageUrl): array
    {
        $host = strtolower((string) parse_url($pageUrl, PHP_URL_HOST));
        preg_match_all('#<a\b[^>]*href\s*=\s*["\']([^"\'\#]+)#i', $html, $matches);

        $links = [];
        foreach ($matches[1] as $href) {
            $href = trim($href);
            if (preg_match('#^(mailto|tel|javascript|whatsapp):#i', $href)) {
                continue;
            }

            $absolute = SafeHttp::absolute($pageUrl, $href);
            if (strtolower((string) parse_url($absolute, PHP_URL_HOST)) === $host) {
                $links[] = $absolute;
            }
        }

        return array_values(array_unique($links));
    }
}
