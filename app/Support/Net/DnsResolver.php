<?php

namespace App\Support\Net;

/** Bound in the container so tests can resolve hosts without the network. */
class DnsResolver
{
    /** @return list<string> IPv4 and IPv6 addresses of $host */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = array_values(array_filter(array_map(fn (array $r) => $r['ip'] ?? $r['ipv6'] ?? null, $records)));

        // The system resolver also reads /etc/hosts; SafeHttp refuses whatever isn't public either way.
        return $ips ?: (gethostbynamel($host) ?: []);
    }
}
