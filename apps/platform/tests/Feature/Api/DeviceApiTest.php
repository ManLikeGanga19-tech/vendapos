<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\EnrolDevice;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use VendaPOS\Core\Enums\BusinessType;

uses(DatabaseMigrations::class);

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete(); // drops each tenant's schema
});

it('lets an enrolled device read its own branch from its tenant’s schema', function () {
    [$tenant, $branch] = tenantWithBranch(BusinessType::Bar);
    $enrolment = enrolFor($tenant, $branch);

    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $enrolment->device->id)
        ->assertOk()
        ->assertJsonPath('device.id', $enrolment->device->id)
        ->assertJsonPath('branch.id', $branch->id)
        ->assertJsonPath('branch.business_type', 'bar')
        ->assertJsonPath('branch.terminal_mode', 'tabs');
});

it('rejects a token presented with another device’s ID and logs it', function () {
    [$tenantA, $branchA] = tenantWithBranch();
    [$tenantB, $branchB] = tenantWithBranch();
    $deviceA = enrolFor($tenantA, $branchA);
    $deviceB = enrolFor($tenantB, $branchB);

    Log::shouldReceive('channel')->once()->with('security')->andReturnSelf();
    Log::shouldReceive('warning')->once()->withArgs(fn (string $message, array $context) => $message === 'api.token_device_mismatch'
        && $context['device_id'] === $deviceA->device->id
        && $context['claimed_device_id'] === $deviceB->device->id);

    deviceRequest('GET', '/api/v1/device', $deviceA->plainTextToken, $deviceB->device->id)
        ->assertUnauthorized()
        ->assertJsonMissing(['name' => $branchB->name]);
});

it('requires the device ID header', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);

    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, null)->assertUnauthorized();
});

it('takes the tenant from the device, never from the request', function () {
    [$tenantA, $branchA] = tenantWithBranch();
    [$tenantB] = tenantWithBranch();
    $enrolment = enrolFor($tenantA, $branchA);

    deviceRequest('GET', '/api/v1/device?tenant='.$tenantB->id.'&tenant_id='.$tenantB->id, $enrolment->plainTextToken,
        $enrolment->device->id, ['X-Tenant' => $tenantB->id])
        ->assertOk()
        ->assertJsonPath('branch.id', $branchA->id);
});

it('ends the tenant context when the request finishes', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);

    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $enrolment->device->id)->assertOk();

    expect(tenancy()->initialized)->toBeFalse();
});

it('records when a device was last seen, at most once a minute', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);
    $this->freezeSecond();

    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $enrolment->device->id)->assertOk();
    $first = $enrolment->device->fresh()?->last_seen_at;

    $this->travel(30)->seconds();
    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $enrolment->device->id)->assertOk();
    $second = $enrolment->device->fresh()?->last_seen_at;

    $this->travel(31)->seconds();
    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $enrolment->device->id)->assertOk();

    expect($first?->toIso8601String())->toBe(now()->subSeconds(61)->toIso8601String())
        ->and($second?->equalTo($first))->toBeTrue()
        ->and($enrolment->device->fresh()?->last_seen_at?->toIso8601String())->toBe(now()->toIso8601String());
});

it('does not mark a rejected request as seen', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);

    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, 'not-this-device')->assertUnauthorized();

    expect($enrolment->device->fresh()?->last_seen_at)->toBeNull();
});

it('rejects revoked devices', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);

    $enrolment->device->revoke();

    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $enrolment->device->id)->assertUnauthorized();
});

it('rejects expired tokens', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);

    $this->travel(config('vendapos.device_token_ttl_hours') + 1)->hours();

    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $enrolment->device->id)->assertUnauthorized();
});

it('rolls a token over and revokes the old one', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);
    $deviceId = $enrolment->device->id;

    $newToken = deviceRequest('POST', '/api/v1/device/token', $enrolment->plainTextToken, $deviceId)
        ->assertCreated()
        ->json('token');

    deviceRequest('GET', '/api/v1/device', $newToken, $deviceId)->assertOk();
    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $deviceId)->assertUnauthorized();
    expect(Device::findOrFail($deviceId)->tokens()->count())->toBe(1);
});

it('keeps the old token working for the overlap while the new one is unused', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);
    $deviceId = $enrolment->device->id;
    $overlap = (int) config('vendapos.device_token_overlap_minutes');

    // The reply with the new token is lost: the till only has the old one.
    deviceRequest('POST', '/api/v1/device/token', $enrolment->plainTextToken, $deviceId)->assertCreated();

    $this->travel($overlap - 1)->minutes();
    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $deviceId)->assertOk();

    $this->travel(2)->minutes();
    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $deviceId)->assertUnauthorized();
});

it('never lengthens the old token past its own expiry', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);
    $deviceId = $enrolment->device->id;

    // Two minutes left: the overlap must not stretch that to the full overlap.
    $this->travel((int) config('vendapos.device_token_ttl_hours') * 60 - 2)->minutes();
    deviceRequest('POST', '/api/v1/device/token', $enrolment->plainTextToken, $deviceId)->assertCreated();

    $this->travel(3)->minutes();
    deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $deviceId)->assertUnauthorized();
});

it('lets the old token refresh again and drops the token that never arrived', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);
    $deviceId = $enrolment->device->id;

    $lost = deviceRequest('POST', '/api/v1/device/token', $enrolment->plainTextToken, $deviceId)->assertCreated()->json('token');
    $received = deviceRequest('POST', '/api/v1/device/token', $enrolment->plainTextToken, $deviceId)->assertCreated()->json('token');

    expect(Device::findOrFail($deviceId)->tokens()->count())->toBe(2);
    deviceRequest('GET', '/api/v1/device', $lost, $deviceId)->assertUnauthorized();
    deviceRequest('GET', '/api/v1/device', $received, $deviceId)->assertOk();
    expect(Device::findOrFail($deviceId)->tokens()->count())->toBe(1);
});

it('retires only the device’s own older tokens on first use', function () {
    [$tenant, $branch] = tenantWithBranch();
    $other = enrolFor($tenant, $branch);
    $enrolment = enrolFor($tenant, $branch);
    $deviceId = $enrolment->device->id;

    $newToken = deviceRequest('POST', '/api/v1/device/token', $enrolment->plainTextToken, $deviceId)->assertCreated()->json('token');
    deviceRequest('GET', '/api/v1/device', $newToken, $deviceId)->assertOk();

    deviceRequest('GET', '/api/v1/device', $other->plainTextToken, $other->device->id)->assertOk();
});

it('keeps a device’s overlap tokens apart from other tenants’ devices', function () {
    [$tenantA, $branchA] = tenantWithBranch();
    [$tenantB, $branchB] = tenantWithBranch();
    $a = enrolFor($tenantA, $branchA);
    $b = enrolFor($tenantB, $branchB);

    deviceRequest('POST', '/api/v1/device/token', $a->plainTextToken, $a->device->id)->assertCreated();

    // A's old token, still in its overlap, is A's alone.
    deviceRequest('GET', '/api/v1/device', $a->plainTextToken, $b->device->id)->assertUnauthorized();
    deviceRequest('GET', '/api/v1/device', $b->plainTextToken, $b->device->id)->assertOk();
    expect(Device::findOrFail($b->device->id)->tokens()->count())->toBe(1);
});

it('rate limits each device', function () {
    config(['vendapos.rate_limits.device' => 2]);
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);

    foreach ([200, 200, 429] as $status) {
        deviceRequest('GET', '/api/v1/device', $enrolment->plainTextToken, $enrolment->device->id)->assertStatus($status);
    }
});

it('refuses to enrol a device against another tenant’s branch', function () {
    [$tenantA] = tenantWithBranch();
    [, $branchB] = tenantWithBranch();

    expect(fn () => app(EnrolDevice::class)->handle($tenantA, $branchB->id, 'Till 1'))
        ->toThrow(InvalidArgumentException::class);

    expect(Device::count())->toBe(0);
});

it('enrols a device from the command line', function () {
    [$tenant, $branch] = tenantWithBranch();

    $this->artisan('devices:enrol', ['tenant' => $tenant->id, 'branch' => $branch->id, 'name' => 'Till 2'])
        ->expectsOutputToContain('enrolled')
        ->assertSuccessful();

    expect(Device::where('tenant_id', $tenant->id)->where('name', 'Till 2')->exists())->toBeTrue();
});
