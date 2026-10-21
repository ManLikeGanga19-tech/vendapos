<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\EnrolDevice;
use App\Domain\Tenancy\Data\DeviceEnrolment;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\BusinessType;

uses(DatabaseMigrations::class);

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete(); // drops each tenant's schema
});

/** @return array{0: Tenant, 1: Branch} */
function tenantWithBranch(BusinessType $type = BusinessType::Restaurant): array
{
    $tenant = Tenant::create(['id' => 't'.Str::lower(Str::random(10))]);

    $branch = $tenant->run(fn () => Branch::create(['name' => 'Nyali '.$tenant->id, 'business_type' => $type]));

    return [$tenant, $branch];
}

function enrolFor(Tenant $tenant, Branch $branch): DeviceEnrolment
{
    return app(EnrolDevice::class)->handle($tenant, $branch->id, 'Till 1');
}

function deviceRequest(string $method, string $uri, string $token, ?string $deviceId, array $headers = []): TestResponse
{
    app('auth')->forgetGuards(); // each request authenticates from scratch, as in production

    $headers = ['Authorization' => "Bearer {$token}", ...$headers];
    if ($deviceId !== null) {
        $headers['X-Device-ID'] = $deviceId;
    }

    return test()->json($method, $uri, [], $headers);
}

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
