<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Network\CloudflareIps;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces Laravel's TrustProxies. The client IP comes from Cloudflare's
 * CF-Connecting-IP, and only when the connection itself is from a
 * Cloudflare edge range (or a proxy we run, such as cloudflared). Cloudflare
 * overwrites that header, so it cannot be forged through Cloudflare, and a
 * direct connection's forwarding headers are ignored. Without this every
 * request would carry a Cloudflare IP: the Safaricom callback allowlist
 * would refuse real callbacks and per-IP limits would lump clients together.
 *
 * Only the client IP and scheme are taken from the proxy. X-Forwarded-Host
 * and -Port are never trusted, because tenancy picks the tenant by host.
 */
final class TrustCloudflare
{
    public const string CLIENT_IP_HEADER = 'CF-Connecting-IP';

    private const int TRUSTED_HEADERS = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO;

    public function __construct(private readonly CloudflareIps $cloudflareIps) {}

    public function handle(Request $request, Closure $next): Response
    {
        $peer = (string) $request->server->get('REMOTE_ADDR');
        $clientIp = (string) $request->headers->get(self::CLIENT_IP_HEADER);

        // Trusted proxies are process-wide in Symfony; reset them on every request (Octane reuses workers).
        Request::setTrustedProxies([], self::TRUSTED_HEADERS);

        if ($this->fromTrustedProxy($peer) && filter_var($clientIp, FILTER_VALIDATE_IP) !== false) {
            // One hop with exactly the client Cloudflare saw; anything the client put in the chain is dropped.
            $request->headers->set('X-Forwarded-For', $clientIp);
            Request::setTrustedProxies([$peer], self::TRUSTED_HEADERS);
        }

        return $next($request);
    }

    private function fromTrustedProxy(string $peer): bool
    {
        if ($peer === '') {
            return false;
        }

        /** @var list<string> $ownProxies */
        $ownProxies = config('vendapos.network.trusted_proxies');

        return IpUtils::checkIp($peer, [...$this->cloudflareIps->current(), ...$ownProxies]);
    }
}
