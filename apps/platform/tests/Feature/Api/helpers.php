<?php

declare(strict_types=1);

/* Shared helpers for API feature tests: real tenants with real schemas, enrolled devices, device-authenticated requests. */

use App\Domain\Tenancy\Actions\EnrolDevice;
use App\Domain\Tenancy\Data\DeviceEnrolment;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\BusinessType;

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
