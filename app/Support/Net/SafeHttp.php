<?php

namespace App\Support\Net;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GETs pages at addresses we don't control (lead sites, some typed by users) without letting them
 * reach the internal network: http/https on web ports only, every hop must resolve to public IPs
 * only, the connection is pinned to the IP that was checked (no DNS rebinding between check and
 * connect), at most 3 redirects, and at most 1 MB is read per response.
 */
class SafeHttp
{
    public const AGENT = 'ZapLeadsBot';
    public const MAX_BYTES = 1_048_576;

    private const MAX_REDIRECTS = 3;
    private const PORTS = [80, 443, 8080, 8443];

    public function __construct(
        private readonly DnsResolver $dns,
    ) {}

    /**
     * @param (callable(string): bool)|null $follow asked before each redirect is followed; false stops there
     * @return array{url: string, status: int, body: string, content_type: string}|null null when blocked, stopped or unreachable
     */
    public function get(string $url, int $timeout = 8, ?callable $follow = null): ?array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = $this->target($url);
            if ($target === null) {
                return null;
            }

            try {
                $response = Http::timeout($timeout)
                    ->withUserAgent(self::AGENT . '/1.0 (+' . config('app.url') . ')')
                    ->withOptions([
                        'allow_redirects' => false,
                        'stream'          => true,
                        'curl'            => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]],
                    ])
                    ->get($url);
            } catch (\Throwable $e) {
                Log::info('SafeHttp request failed', ['url' => $url, 'error' => $e->getMessage()]);
                return null;
            }

            if ($response->redirect()) {
                $location = $response->header('Location');
                if ($location === '') {
                    return null;
                }
                $url = self::absolute($url, $location);
                if ($follow !== null && !$follow($url)) {
                    return null;
                }
                continue;
            }

            return [
                'url'          => $url,
                'status'       => $response->status(),
                'body'         => self::readLimited($response),
                'content_type' => $response->header('Content-Type'),
            ];
        }

        Log::info('SafeHttp gave up after redirects', ['url' => $url]);

        return null;
    }

    /** Whether $url may be fetched at all (scheme, port and public address), without fetching it. */
    public function allows(string $url): bool
    {
        return $this->target($url) !== null;
    }

    /** @return array{host: string, port: int, ip: string}|null */
    private function target(string $url): ?array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user'])) {
            return null;
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, self::PORTS, true)) {
            return null;
        }

        $ips = $this->dns->resolve($host);

        // Every address must be public: a host answering with one public and one private IP is refused.
        if ($ips === [] || array_filter($ips, fn (string $ip) => !self::isPublic($ip)) !== []) {
            Log::info('SafeHttp blocked address', ['url' => $url, 'ips' => $ips]);
            return null;
        }

        $ip = $ips[0];

        return ['host' => $host, 'port' => $port, 'ip' => str_contains($ip, ':') ? "[{$ip}]" : $ip];
    }

    private static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }

    private static function readLimited(Response $response): string
    {
        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (!$stream->eof() && strlen($body) < self::MAX_BYTES) {
            $chunk = $stream->read(65_536);
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        return substr($body, 0, self::MAX_BYTES);
    }

    /** Resolves a Location header (absolute, protocol-relative, absolute path or relative path) against $base. */
    public static function absolute(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $parts['scheme'] . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $dir = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/');

        return $origin . ($dir ?: '/') . $location;
    }
}
