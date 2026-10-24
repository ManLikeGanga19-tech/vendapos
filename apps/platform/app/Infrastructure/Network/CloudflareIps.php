<?php

declare(strict_types=1);

namespace App\Infrastructure\Network;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cloudflare's edge address ranges: only requests from these may tell us
 * the real client IP. The list is refreshed daily from Cloudflare's API
 * into the cache; until a refresh has succeeded, the copy in config
 * (vendapos.network.cloudflare_ips) is used.
 */
final class CloudflareIps
{
    public const string CACHE_KEY = 'network:cloudflare-ips';

    /** @return list<string> CIDR ranges */
    public function current(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            /** @var list<string> $cached */
            return $cached;
        }

        /** @var list<string> $fallback */
        $fallback = config('vendapos.network.cloudflare_ips');

        return $fallback;
    }

    /**
     * Fetches the ranges and stores them only if the whole answer is sound,
     * so a bad or partial response never changes who is trusted.
     *
     * @return list<string>
     *
     * @throws RuntimeException
     */
    public function refresh(): array
    {
        $response = Http::acceptJson()->timeout(10)->retry(2, 500, throw: false)
            ->get((string) config('vendapos.network.cloudflare_ips_url'));

        if (! $response->successful() || $response->json('success') !== true) {
            throw new RuntimeException("Cloudflare IP list request failed (HTTP {$response->status()}).");
        }

        $v4 = $response->json('result.ipv4_cidrs');
        $v6 = $response->json('result.ipv6_cidrs');

        if (! is_array($v4) || ! is_array($v6) || $v4 === [] || $v6 === []) {
            throw new RuntimeException('Cloudflare IP list is missing its IPv4 or IPv6 ranges.');
        }

        $ranges = array_values([...$v4, ...$v6]);

        foreach ($ranges as $range) {
            if (! is_string($range) || ! $this->isNarrowCidr($range)) {
                throw new RuntimeException('Cloudflare IP list has an invalid range: '.json_encode($range));
            }
        }

        /** @var list<string> $ranges */
        Cache::forever(self::CACHE_KEY, $ranges);

        return $ranges;
    }

    /**
     * A valid CIDR no wider than /8 (IPv4) or /16 (IPv6). Cloudflare's widest
     * ranges are far narrower; this refuses anything like 0.0.0.0/0 that
     * would make every caller trusted.
     */
    private function isNarrowCidr(string $range): bool
    {
        if (preg_match('/^([0-9a-fA-F:.]+)\/(\d{1,3})$/', $range, $parts) !== 1) {
            return false;
        }

        $bits = (int) $parts[2];

        if (filter_var($parts[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $bits >= 8 && $bits <= 32;
        }

        if (filter_var($parts[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $bits >= 16 && $bits <= 128;
        }

        return false;
    }
}
