<?php

declare(strict_types=1);

/* Shared helpers for API feature tests: real tenants with real schemas, enrolled devices, device-authenticated requests. */

use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Actions\EnrolDevice;
use App\Domain\Tenancy\Data\DeviceEnrolment;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Tax\OrderTotals;

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

/**
 * A correct order payload whose totals are computed with the shared core maths.
 *
 * @param  list<array{0: Item, 1: int}>  $lines
 * @return array<string, mixed>
 */
function saleOf(array $lines, int $sequence = 1, array $overrides = []): array
{
    $lineData = array_map(fn (array $line) => [
        'item_id' => $line[0]->id,
        'name' => $line[0]->name,
        'quantity' => $line[1],
        'unit_price_cents' => $line[0]->price_cents,
        'vat_category' => $line[0]->vat_category->value,
        'line_total_cents' => $line[0]->price_cents * $line[1],
    ], $lines);

    $totals = OrderTotals::fromLines(array_map(fn (array $l) => OrderLineData::from($l), $lineData));

    return array_merge([
        'uuid' => (string) Str::uuid(),
        'sequence_number' => $sequence,
        'placed_at' => '2026-10-21T19:15:00+03:00',
        'status' => 'completed',
        'lines' => $lineData,
        'payment_type' => 'cash',
        'subtotal_cents' => $totals->subtotal->cents,
        'vat_cents' => $totals->vat->cents,
        'total_cents' => $totals->total->cents,
    ], $overrides);
}

/** POST a batch as the test's enrolled device. */
function sync(array $orders): TestResponse
{
    $enrolment = test()->enrolment;
    app('auth')->forgetGuards();

    return test()->withHeaders([
        'Authorization' => 'Bearer '.$enrolment->plainTextToken,
        'X-Device-ID' => $enrolment->device->id,
    ])->postJson('/api/v1/orders/sync', ['orders' => $orders]);
}
