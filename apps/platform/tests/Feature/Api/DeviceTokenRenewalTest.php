<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

beforeEach(function () {
    // The limiter counts in real Redis; fresh keys per test, and a clock held
    // at a known point so no request in a test crosses a window boundary.
    config(['vendapos.rate_limits.token_renewal.key_prefix' => 'test:token-renewal:'.Str::uuid().':']);
    $this->travelTo(now()->startOfHour()->addMinutes(5));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

const RENEW = '/api/v1/device/token/renew';

/** Moves time to $minutes after the device's first token expired. */
function pastExpiry(int $minutes): void
{
    test()->travel((int) config('vendapos.device_token_ttl_hours') * 60 + $minutes)->minutes();
}

it('renews a token that expired within the grace window, once', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);
    $deviceId = $enrolment->device->id;
    pastExpiry(3 * 24 * 60);

    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $deviceId)->assertUnauthorized();

    $response = deviceRequest('POST', RENEW, $enrolment->plainTextToken, $deviceId)->assertCreated();

    expect($response->json('expires_at'))->toBe(now()->addHours((int) config('vendapos.device_token_ttl_hours'))->toIso8601String());
    deviceRequest('GET', '/api/v1/device', $response->json('token'), $deviceId)->assertOk();
    expect(Device::findOrFail($deviceId)->tokens()->count())->toBe(1);

    // The old token is gone: a second renewal with it (a retry, or a racing request) gets nothing.
    deviceRequest('POST', RENEW, $enrolment->plainTextToken, $deviceId)->assertUnauthorized();
    expect(Device::findOrFail($deviceId)->tokens()->count())->toBe(1);
});

it('renews up to the end of the grace window and not after', function (int $minutesPastGrace, int $status) {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);
    pastExpiry((int) config('vendapos.device_token_grace_days') * 24 * 60 + $minutesPastGrace);

    deviceRequest('POST', RENEW, $enrolment->plainTextToken, $enrolment->device->id)->assertStatus($status);
})->with([
    'a minute before the end' => [-1, 201],
    'a minute after the end' => [1, 401],
]);

it('sends a still-valid token to the normal refresh', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);

    deviceRequest('POST', RENEW, $enrolment->plainTextToken, $enrolment->device->id)->assertConflict();

    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $enrolment->device->id)->assertOk();
});

it('refuses another device’s ID, across tenants too, and logs it', function () {
    [$tenantA, $branchA] = tenantWithBranch();
    [$tenantB, $branchB] = tenantWithBranch();
    $deviceA = enrolFor($tenantA, $branchA);
    $deviceB = enrolFor($tenantB, $branchB);
    pastExpiry(60);

    Log::shouldReceive('channel')->with('security')->andReturnSelf();
    Log::shouldReceive('warning')->once()->withArgs(fn (string $message, array $context) => $message === 'api.token_renewal_token_device_mismatch'
        && $context['device_id'] === $deviceA->device->id
        && $context['claimed_device_id'] === $deviceB->device->id);

    deviceRequest('POST', RENEW, $deviceA->plainTextToken, $deviceB->device->id)
        ->assertUnauthorized()
        ->assertJsonMissingPath('token');

    expect($deviceA->device->tokens()->count())->toBe(1)
        ->and($deviceB->device->tokens()->count())->toBe(1);
});

it('refuses a revoked device', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);
    $enrolment->device->forceFill(['revoked_at' => now()])->save(); // revoked, token row left behind
    pastExpiry(60);

    deviceRequest('POST', RENEW, $enrolment->plainTextToken, $enrolment->device->id)->assertUnauthorized();
    expect($enrolment->device->tokens()->count())->toBe(1);
});

it('refuses a missing device ID, a wrong secret and tokens we did not issue', function (Closure $request) {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);
    pastExpiry(60);

    $request($enrolment)->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    expect($enrolment->device->tokens()->count())->toBe(1);
})->with([
    'no device ID' => [fn ($e) => deviceRequest('POST', RENEW, $e->plainTextToken, null)],
    'wrong secret' => [fn ($e) => deviceRequest('POST', RENEW, Str::before($e->plainTextToken, '|').'|'.Str::random(40), $e->device->id)],
    'no id prefix' => [fn ($e) => deviceRequest('POST', RENEW, Str::after($e->plainTextToken, '|'), $e->device->id)],
    'no token' => [fn ($e) => test()->postJson(RENEW, [], ['X-Device-ID' => $e->device->id])],
]);

it('refuses a token that never expires', function () {
    [$tenant, $branch] = tenantWithBranch();
    $device = enrolFor($tenant, $branch)->device;
    $forever = $device->createToken('forever');

    deviceRequest('POST', RENEW, $forever->plainTextToken, $device->id)->assertUnauthorized();
});

it('limits renewals per IP per minute, counting failures', function () {
    config(['vendapos.rate_limits.token_renewal.per_minute' => 3]);

    foreach ([401, 401, 401, 429, 429] as $status) {
        // A new device ID each time, so only the IP limit applies.
        deviceRequest('POST', RENEW, '1|'.Str::random(40), (string) Str::uuid())->assertStatus($status);
    }

    $this->travel(61)->seconds();
    deviceRequest('POST', RENEW, '1|'.Str::random(40), (string) Str::uuid())->assertUnauthorized();
});

it('limits renewals per device ID across IPs', function () {
    config(['vendapos.rate_limits.token_renewal.per_minute' => 2]);
    $deviceId = (string) Str::uuid();

    foreach (['10.0.0.1' => 401, '10.0.0.2' => 401, '10.0.0.3' => 429] as $ip => $status) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);
        deviceRequest('POST', RENEW, '1|'.Str::random(40), $deviceId)->assertStatus($status);
    }
});

it('limits renewals per hour, and rejected requests use up the hour', function () {
    config(['vendapos.rate_limits.token_renewal.per_minute' => 2, 'vendapos.rate_limits.token_renewal.per_hour' => 4]);

    foreach ([401, 401, 429, 429] as $status) {
        deviceRequest('POST', RENEW, '1|'.Str::random(40), (string) Str::uuid())->assertStatus($status);
    }

    // A new minute, but the hour already holds 4 requests.
    $this->travel(61)->seconds();
    deviceRequest('POST', RENEW, '1|'.Str::random(40), (string) Str::uuid())
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('logs a throttled caller once per window, not on every request', function () {
    config(['vendapos.rate_limits.token_renewal.per_minute' => 1]);
    $deviceId = (string) Str::uuid();
    $throttleLogs = 0;
    Log::shouldReceive('channel')->with('security')->andReturnSelf();
    Log::shouldReceive('warning')->andReturnUsing(function (string $message) use (&$throttleLogs) {
        $throttleLogs += $message === 'api.token_renewal_throttled' ? 1 : 0;
    });

    foreach (range(1, 5) as $ignored) {
        deviceRequest('POST', RENEW, '1|'.Str::random(40), $deviceId);
    }

    expect($throttleLogs)->toBe(2); // one per subject: the IP and the device ID
});

it('counts every request in Redis, so the limit is exact', function () {
    config(['vendapos.rate_limits.token_renewal.per_minute' => 10]);

    $statuses = collect(range(1, 25))->map(
        fn () => deviceRequest('POST', RENEW, '1|'.Str::random(40), (string) Str::uuid())->status(),
    );

    expect($statuses->filter(fn (int $status) => $status !== 429))->toHaveCount(10)
        ->and($statuses->slice(10)->unique()->all())->toBe([10 => 429]);
});
