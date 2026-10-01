<?php

namespace App\Services\Enrichment\Steps;

use App\Models\Lead;
use App\Services\Enrichment\EnrichmentContext;
use App\Services\Enrichment\EnrichmentStep;
use App\Services\Enrichment\WebsiteContactExtractor;
use App\Support\Net\RobotsTxt;
use App\Support\Net\SafeHttp;

/**
 * Reads up to 4 pages of the lead's own site (home + contact/about pages), respecting robots.txt.
 * A wa.me link there is the strongest WhatsApp evidence there is: the business published it.
 */
class WebsiteScrapeStep implements EnrichmentStep
{
    private const MAX_PAGES = 4;
    private const PAGE_HINTS = '#/(contato|contact|fale-conosco|faleconosco|sobre|quem-somos|about)#i';
    private const GUESSED_PATHS = ['/contato', '/fale-conosco', '/sobre', '/quem-somos', '/contact'];
    private const ABOUT_PAGES = '#/(sobre|quem-somos|about)#i';

    /** @var array<string, RobotsTxt> by origin */
    private array $robots = [];

    public function __construct(
        private readonly SafeHttp $http,
    ) {}

    public function run(Lead $lead, EnrichmentContext $ctx): void
    {
        $site = trim((string) $lead->website);
        if ($site === '') {
            return;
        }
        // "studiobella.com.br" typed without a scheme; other schemes are refused by SafeHttp.
        if (!str_contains($site, '://')) {
            $site = 'https://' . $site;
        }

        // Google listings often give the WhatsApp link or a social profile as the "site".
        if ($this->collectFromAddress($lead, $ctx, $site)) {
            return;
        }

        $home = $this->fetch($site, $ctx);
        if ($home === null) {
            return;
        }

        $homeFound = WebsiteContactExtractor::extract($home['body'], $home['url'], $ctx->region);
        $pages = [[$home['url'], $homeFound]];

        foreach ($this->candidatePages($home['url'], $homeFound['links']) as $url) {
            if (count($pages) >= self::MAX_PAGES) {
                break;
            }
            if ($page = $this->fetch($url, $ctx)) {
                $pages[] = [$page['url'], WebsiteContactExtractor::extract($page['body'], $page['url'], $ctx->region)];
            }
        }

        $about = null;
        foreach ($pages as [$pageUrl, $found]) {
            $this->collect($lead, $ctx, $found, $pageUrl);

            if ($about === null && preg_match(self::ABOUT_PAGES, (string) parse_url($pageUrl, PHP_URL_PATH)) && $found['text'] !== '') {
                $about = mb_substr($found['text'], 0, 1500);
            }
        }

        if ($about !== null) {
            $lead->dossie = [...($lead->dossie ?? []), 'sobre' => $about];
        }
    }

    /**
     * True when the address is itself a WhatsApp link or a social profile: nothing to read there,
     * and Instagram/Facebook pages are never scraped (their terms forbid it).
     */
    private function collectFromAddress(Lead $lead, EnrichmentContext $ctx, string $site): bool
    {
        $host = strtolower((string) parse_url($site, PHP_URL_HOST));
        $where = mb_substr($site, 0, 200);

        if ($numbers = WebsiteContactExtractor::whatsappLinks($site, $ctx->region)) {
            foreach ($numbers as $e164) {
                $ctx->addPhone($e164, 'website_wa_link', whatsapp: true, evidencia: "link de WhatsApp informado como site ({$where})");
            }
            return true;
        }

        if (preg_match('/(^|\.)instagram\.com$/', $host)) {
            if ($handle = WebsiteContactExtractor::instagramHandle($site)) {
                $ctx->add('instagram', $handle, 'google_places', "perfil informado como site ({$where})");
            }
            return true;
        }

        if (preg_match('/(^|\.)(facebook\.com|fb\.com)$/', $host)) {
            $ctx->add('facebook', strtok($site, '?'), 'google_places', 'perfil informado como site');
            return true;
        }

        return false;
    }

    private function collect(Lead $lead, EnrichmentContext $ctx, array $found, string $url): void
    {
        $where = mb_substr($url, 0, 200);

        foreach ($found['whatsapp'] as $e164) {
            $ctx->addPhone($e164, 'website_wa_link', whatsapp: true, evidencia: "link de WhatsApp em {$where}");
        }
        foreach ($found['whatsapp_text'] as $raw) {
            $ctx->addPhone($raw, 'website_wa_text', whatsapp: true, evidencia: "\"WhatsApp\" no texto de {$where}");
        }
        foreach ($found['tel'] as $raw) {
            $ctx->addPhone($raw, 'website_tel', evidencia: "link tel: em {$where}");
        }
        foreach ($found['emails'] as $email) {
            $ctx->add('email', $email, 'website', $where);
        }
        foreach ($found['instagram'] as $handle) {
            $ctx->add('instagram', $handle, 'website', $where);
        }
        foreach ($found['facebook'] as $profile) {
            $ctx->add('facebook', $profile, 'website', $where);
        }

        if (!$lead->cnpj && $found['cnpjs'] !== []) {
            $lead->cnpj = $found['cnpjs'][0];
        }
    }

    /** Contact/about pages linked from the home page first, then the usual paths. */
    private function candidatePages(string $homeUrl, array $links): array
    {
        $origin = self::origin($homeUrl);
        $linked = array_filter($links, fn (string $link) => preg_match(self::PAGE_HINTS, (string) parse_url($link, PHP_URL_PATH)));
        $guessed = array_map(fn (string $path) => $origin . $path, self::GUESSED_PATHS);

        $urls = [];
        foreach ([...$linked, ...$guessed] as $url) {
            $key = rtrim(strtok($url, '?'), '/');
            if ($key !== rtrim($homeUrl, '/')) {
                $urls[$key] = $url;
            }
        }

        return array_values($urls);
    }

    /**
     * An HTML page robots.txt lets us read. Every redirect hop is checked against its own host's
     * robots.txt before it is followed; one that leads to a WhatsApp link (wa.link and other
     * shorteners) is recorded and not followed.
     *
     * @return array{url: string, status: int, body: string, content_type: string}|null
     */
    private function fetch(string $url, EnrichmentContext $ctx): ?array
    {
        if (!$this->allowed($url)) {
            return null;
        }

        $page = $this->http->get($url, follow: function (string $next) use ($ctx, $url): bool {
            if ($numbers = WebsiteContactExtractor::whatsappLinks($next, $ctx->region)) {
                foreach ($numbers as $e164) {
                    $ctx->addPhone($e164, 'website_wa_link', whatsapp: true, evidencia: mb_substr("{$url} redireciona para o WhatsApp", 0, 255));
                }
                return false;
            }

            return $this->allowed($next);
        });

        if ($page === null || $page['status'] >= 400 || $page['body'] === '') {
            return null;
        }

        return $page['content_type'] === '' || str_contains(strtolower($page['content_type']), 'html') ? $page : null;
    }

    private function allowed(string $url): bool
    {
        return $this->robotsFor(self::origin($url))->allows((string) (parse_url($url, PHP_URL_PATH) ?: '/'));
    }

    private function robotsFor(string $origin): RobotsTxt
    {
        if (!isset($this->robots[$origin])) {
            $file = $this->http->get($origin . '/robots.txt', 5);
            $content = $file !== null && $file['status'] === 200 ? $file['body'] : '';
            $this->robots[$origin] = RobotsTxt::parse($content, SafeHttp::AGENT);
        }

        return $this->robots[$origin];
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);

        return strtolower($parts['scheme'] ?? 'https') . '://' . strtolower($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
