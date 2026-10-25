<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * A strict limiter for the unauthenticated device routes (token renewal,
 * pairing), named by its config under vendapos.rate_limits, e.g.
 * `throttle.strict:token_renewal`. Each configured subject (client IP, the
 * claimed device ID, or every caller together) is limited per minute and
 * per hour. Laravel's throttle middleware checks and then counts, and its
 * counter can lose hits when a window starts under concurrent requests, so
 * more than the limit get through. Here one Redis script increments every
 * counter atomically before anything is compared. Windows are fixed clock
 * buckets (the key carries the bucket number), and every request counts,
 * rejected ones included.
 */
final class ThrottleStrictly
{
    /** INCR each key, giving a new key its expiry; returns the counts in key order. */
    private const string SCRIPT = <<<'LUA'
        local counts = {}
        for i, key in ipairs(KEYS) do
            local count = redis.call('INCR', key)
            if count == 1 then redis.call('EXPIRE', key, ARGV[i]) end
            counts[i] = count
        end
        return counts
        LUA;

    public function handle(Request $request, Closure $next, string $limiter): Response
    {
        $config = "vendapos.rate_limits.{$limiter}";
        $now = now()->getTimestamp();
        $prefix = (string) config("{$config}.key_prefix");

        $limits = [];
        foreach ($this->subjects($request, (array) config("{$config}.subjects")) as $kind => $subject) {
            foreach ($this->windows($config, $kind) as $window => [$max, $seconds]) {
                $bucket = intdiv($now, $seconds);
                $limits[] = [
                    'key' => "{$prefix}{$kind}:{$window}:{$bucket}:{$subject}",
                    'max' => $max,
                    'name' => "{$kind}_per_{$window}",
                    'retry_after' => ($bucket + 1) * $seconds - $now,
                    // Kept a little past the window so a slow clock never reopens it.
                    'ttl' => $seconds + 60,
                ];
            }
        }

        // phpredis is the configured client; Laravel's wrapper takes (script, number of keys, ...keys and args).
        /** @var PhpRedisConnection $redis */
        $redis = Redis::connection((string) config("{$config}.redis_connection"));

        /** @var list<int> $counts */
        $counts = $redis->eval(
            self::SCRIPT,
            count($limits),
            ...array_column($limits, 'key'),
            ...array_column($limits, 'ttl'),
        );

        $retryAfter = 0;

        foreach ($limits as $i => $limit) {
            $count = (int) $counts[$i];

            if ($count <= $limit['max']) {
                continue;
            }

            $retryAfter = max($retryAfter, $limit['retry_after']);

            // Logged once per window, not on every request of a flood.
            if ($count === $limit['max'] + 1) {
                Log::channel('security')->warning("api.{$limiter}_throttled", [
                    'limit' => $limit['name'],
                    'ip' => $request->ip(),
                    'claimed_device_id' => $request->header(EnsureDeviceBinding::HEADER),
                ]);
            }
        }

        if ($retryAfter > 0) {
            return response()->json(['message' => 'Too Many Attempts.'], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        return $next($request);
    }

    /**
     * @param  array<mixed>  $kinds
     * @return array<string, string> kind => subject
     */
    private function subjects(Request $request, array $kinds): array
    {
        $subjects = [];

        foreach ($kinds as $kind) {
            $subjects[(string) $kind] = match ($kind) {
                'ip' => (string) $request->ip(),
                // The header is client-controlled, so it is hashed to bound the key length.
                'device' => hash('sha256', (string) $request->header(EnsureDeviceBinding::HEADER)),
                // Every caller together: a ceiling no number of addresses gets past.
                'all' => 'all',
                default => throw new \InvalidArgumentException("Unknown rate limit subject [{$kind}]."),
            };
        }

        return $subjects;
    }

    /** @return array<string, array{0: int, 1: int}> window => [max requests, seconds] */
    private function windows(string $config, string $kind): array
    {
        // A subject may have its own ceiling (e.g. all_per_minute); otherwise the shared one.
        return [
            'minute' => [(int) config("{$config}.{$kind}_per_minute", config("{$config}.per_minute")), 60],
            'hour' => [(int) config("{$config}.{$kind}_per_hour", config("{$config}.per_hour")), 3600],
        ];
    }
}
