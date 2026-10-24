<?php

declare(strict_types=1);

use App\Infrastructure\Network\CloudflareIps;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

const CLOUDFLARE_EDGE = '172.64.10.20';  // inside 172.64.0.0/13
const CLIENT_IP = '41.90.64.12';         // a Safaricom home-broadband address
const OUTSIDER = '203.0.113.9';

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    Route::get('/_probe', fn (Request $request) => [
        'ip' => $request->ip(),
        'host' => $request->getHost(),
        'secure' => $request->isSecure(),
    ]);
});

/** @param array<string, string> $headers */
function probe(string $peer, array $headers = []): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $peer])->getJson('/_probe', $headers);
}

/** Cloudflare's /client/v4/ips answer. */
function cloudflareAnswer(array $v4 = ['198.51.100.0/24'], array $v6 = ['2001:db8::/32'], bool $success = true): array
{
    return ['result' => ['ipv4_cidrs' => $v4, 'ipv6_cidrs' => $v6], 'success' => $success];
}

it('takes the client IP and scheme from Cloudflare', function () {
    probe(CLOUDFLARE_EDGE, ['CF-Connecting-IP' => CLIENT_IP, 'X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertJson(['ip' => CLIENT_IP, 'secure' => true]);
});

it('ignores forwarding headers on a direct connection', function () {
    probe(OUTSIDER, [
        'CF-Connecting-IP' => CLIENT_IP,
        'X-Forwarded-For' => CLIENT_IP,
        'X-Forwarded-Proto' => 'https',
    ])->assertJson(['ip' => OUTSIDER, 'secure' => false]);
});

it('drops a forwarded chain the client wrote, keeping only the IP Cloudflare saw', function () {
    probe(CLOUDFLARE_EDGE, [
        'CF-Connecting-IP' => CLIENT_IP,
        'X-Forwarded-For' => '196.201.214.200, '.CLIENT_IP, // a forged Safaricom IP first
    ])->assertJson(['ip' => CLIENT_IP]);
});

it('never takes the host from a proxy, as the host picks the tenant', function () {
    $host = probe(CLOUDFLARE_EDGE, ['CF-Connecting-IP' => CLIENT_IP, 'X-Forwarded-Host' => 'danamo.localhost'])
        ->json('host');

    expect($host)->not->toBe('danamo.localhost');
});

it('keeps the edge address when Cloudflare sent no valid client IP', function (array $headers) {
    probe(CLOUDFLARE_EDGE, $headers)->assertJson(['ip' => CLOUDFLARE_EDGE]);
})->with([
    'no header' => [[]],
    'not an IP' => [['CF-Connecting-IP' => 'localhost']],
    'a list' => [['CF-Connecting-IP' => CLIENT_IP.', 10.0.0.1']],
]);

it('trusts our own proxies, such as cloudflared, from config', function () {
    config(['vendapos.network.trusted_proxies' => ['10.8.0.0/24']]);

    probe('10.8.0.5', ['CF-Connecting-IP' => CLIENT_IP])->assertJson(['ip' => CLIENT_IP]);
    probe('10.9.0.5', ['CF-Connecting-IP' => CLIENT_IP])->assertJson(['ip' => '10.9.0.5']);
});

it('refreshes the edge ranges and trusts the new list only', function () {
    Http::fake(['api.cloudflare.com/client/v4/ips' => Http::response(cloudflareAnswer())]);

    $this->artisan('network:refresh-cloudflare-ips')->assertSuccessful();

    expect(app(CloudflareIps::class)->current())->toBe(['198.51.100.0/24', '2001:db8::/32']);
    probe('198.51.100.7', ['CF-Connecting-IP' => CLIENT_IP])->assertJson(['ip' => CLIENT_IP]);
    probe(CLOUDFLARE_EDGE, ['CF-Connecting-IP' => CLIENT_IP])->assertJson(['ip' => CLOUDFLARE_EDGE]);
});

it('keeps the current list when Cloudflare’s answer is unusable', function (Closure $answer) {
    Http::fake(['api.cloudflare.com/client/v4/ips' => $answer]);
    Log::shouldReceive('channel')->with('security')->andReturnSelf();
    Log::shouldReceive('warning')->once()->withArgs(fn (string $message) => $message === 'network.cloudflare_ips_refresh_failed');

    $this->artisan('network:refresh-cloudflare-ips')->assertFailed();

    expect(app(CloudflareIps::class)->current())->toBe(config('vendapos.network.cloudflare_ips'));
})->with([
    'server error' => [fn () => Http::response('oops', 500)],
    'success false' => [fn () => Http::response(cloudflareAnswer(success: false))],
    'no IPv6 ranges' => [fn () => Http::response(cloudflareAnswer(v6: []))],
    'everything trusted' => [fn () => Http::response(cloudflareAnswer(v4: ['0.0.0.0/0']))],
    'not a range' => [fn () => Http::response(cloudflareAnswer(v4: ['198.51.100.7']))],
]);

it('schedules the refresh daily', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'network:refresh-cloudflare-ips'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 4 * * *')
        ->and($event->timezone)->toBe('Africa/Nairobi');
});
