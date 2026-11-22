<?php

declare(strict_types=1);

/* Shared helpers for API feature tests: real tenants with real schemas, enrolled devices, device-authenticated requests. */

use App\Domain\Catalogue\Models\Item;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Tenancy\Actions\EnrolDevice;
use App\Domain\Tenancy\Data\DeviceEnrolment;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Tax\OrderTotals;

/** A cashier at $tenant to make test sales, created once per tenant. */
function sellerFor(Tenant $tenant): string
{
    static $sellers = [];

    return $sellers[$tenant->id] ??= $tenant->run(function () {
        $staff = Staff::create(['name' => 'Achieng Seller']);
        $staff->assignRole('cashier');

        return $staff->id;
    });
}

/** An owner at $tenant who approves test voids (owners may approve at every branch), created once per tenant. */
function approverFor(Tenant $tenant): string
{
    static $approvers = [];

    return $approvers[$tenant->id] ??= $tenant->run(function () {
        $staff = Staff::create(['name' => 'Wanjiru Owner']);
        $staff->assignRole('owner');

        return $staff->id;
    });
}

/** The tenant a test's sale belongs to: the enrolled till's, else the test's own. */
function saleTenant(): Tenant
{
    $tenantId = isset(test()->enrolment) ? test()->enrolment->device->tenant_id : test()->tenant->id;

    return Tenant::query()->findOrFail($tenantId);
}

/** @return array{0: Tenant, 1: Branch} */
function tenantWithBranch(BusinessType $type = BusinessType::Restaurant): array
{
    $tenant = Tenant::create(['id' => 't'.Str::lower(Str::random(10)), 'modules' => [$type->value]]);

    $branch = $tenant->run(fn () => Branch::create(['name' => 'Nyali '.$tenant->id, 'business_type' => $type]));
    paymentAccountFor($tenant, ['name' => 'Default', 'is_default' => true]);

    return [$tenant, $branch];
}

/** A sandbox M-Pesa account, written directly (no key check, audit or alert). */
function paymentAccountFor(Tenant $tenant, array $overrides = []): PaymentAccount
{
    return $tenant->run(fn () => PaymentAccount::create(array_merge([
        'name' => 'Paybill '.Str::random(4),
        'provider' => 'mpesa_ke',
        'environment' => PaymentAccount::SANDBOX,
        'credentials' => [
            'consumer_key' => 'sandbox-consumer-key',
            'consumer_secret' => 'sandbox-consumer-secret',
            'passkey' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
            'shortcode' => '174379',
            'party_b' => '174379',
            'transaction_type' => 'CustomerPayBillOnline',
        ],
        'is_default' => false,
    ], $overrides)));
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

    // Paid in exact cash, unless cancelled (never paid) or the test brings its own tenders.
    $cancelled = ($overrides['status'] ?? 'completed') === 'cancelled';

    $sale = array_merge([
        'uuid' => (string) Str::uuid(),
        'sequence_number' => $sequence,
        'placed_at' => '2026-10-21T19:15:00+03:00',
        'staff_id' => sellerFor(saleTenant()),
        'status' => 'completed',
        'lines' => $lineData,
        'tenders' => $cancelled ? [] : [cashTender($totals->total->cents)],
        'subtotal_cents' => $totals->subtotal->cents,
        'vat_cents' => $totals->vat->cents,
        'total_cents' => $totals->total->cents,
    ], $overrides);

    // Rung up and voided before it synced: it carries its void, as a till sends it.
    if ($sale['status'] === 'voided' && ! array_key_exists('void', $overrides)) {
        $sale['void'] = voidOf($sale)['void'];
    }

    return $sale;
}

/**
 * $sale as its till resends it once voided: who voided it, who approved it and why.
 *
 * @param  array<string, mixed>  $sale
 * @return array<string, mixed>
 */
function voidOf(array $sale, array $void = []): array
{
    return [...$sale, 'status' => 'voided', 'void' => array_merge([
        'voided_at' => Carbon::parse($sale['placed_at'])->addMinutes(10)->toIso8601String(),
        'voided_by' => $sale['staff_id'],
        'approved_by' => approverFor(saleTenant()),
        'reason' => 'wrong_item',
        'note' => null,
    ], $void)];
}

/**
 * A cash tender payload: $tendered handed over (exact by default), change given back.
 *
 * @return array<string, mixed>
 */
function cashTender(int $amount, ?int $tendered = null): array
{
    $tendered ??= $amount;

    return ['method' => 'cash', 'amount_cents' => $amount, 'tendered_cents' => $tendered, 'change_cents' => $tendered - $amount];
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

/** @return array<string, mixed> */
function paymentOf(array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'order_uuid' => (string) Str::uuid(),
        'provider' => 'mpesa_ke',
        'phone' => '0712 345 678',
        'amount_cents' => 185_000,
    ], $overrides);
}

/** POST a payment request as the test's enrolled device. */
function requestPayment(array $payment): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withHeaders([
        'Authorization' => 'Bearer '.test()->enrolment->plainTextToken,
        'X-Device-ID' => test()->enrolment->device->id,
    ])->postJson('/api/v1/payments', $payment);
}

function paymentIn(Tenant $tenant, string $uuid): Payment
{
    return $tenant->run(fn () => Payment::query()->findOrFail($uuid));
}
