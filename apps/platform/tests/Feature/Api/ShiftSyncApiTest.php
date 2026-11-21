<?php

declare(strict_types=1);

use App\Domain\CashUp\Models\CashMovement;
use App\Domain\CashUp\Models\Shift;
use App\Domain\Catalogue\Models\Item;
use App\Domain\Orders\Models\Order;
use App\Domain\Tenancy\Data\DeviceEnrolment;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\CashMovementKind;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    [$this->tenant, $this->branch] = tenantWithBranch(BusinessType::Shop);
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->cashier = sellerFor($this->tenant);
    $this->manager = approverFor($this->tenant);
    $this->soda = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Soda', 'price_cents' => 8_000, 'vat_category' => VatCategory::Standard]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** @return array<string, mixed> a shift as its till sends it, opened with KES 5,000 */
function shiftOf(array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'number' => 1,
        'opened_at' => '2026-11-21T07:00:00+03:00',
        'opened_by' => test()->cashier,
        'opening_float' => ['counts' => ['1000' => 4, '500' => 2]],
        'movements' => [],
    ], $overrides);
}

/** @return array<string, mixed> $shift closed with $counts, the till expecting $expected */
function closed(array $shift, array $counts, int $expected, array $overrides = []): array
{
    return array_merge($shift, [
        'closed_at' => '2026-11-21T15:00:00+03:00',
        'closed_by' => test()->cashier,
        'closing_count' => ['counts' => $counts],
        'expected_cents' => $expected,
    ], $overrides);
}

/** @return array<string, mixed> */
function movement(CashMovementKind $kind, int $cents, array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'kind' => $kind->value,
        'amount_cents' => $cents,
        'moved_at' => '2026-11-21T12:00:00+03:00',
        'staff_id' => test()->cashier,
        'approved_by' => $kind === CashMovementKind::VoidRefund ? null : test()->manager,
        'reason' => $kind === CashMovementKind::PayOut ? 'Charcoal' : null,
        'order_uuid' => $kind === CashMovementKind::VoidRefund ? (string) Str::uuid() : null,
    ], $overrides);
}

function syncShifts(array $shifts, ?DeviceEnrolment $as = null): TestResponse
{
    $as ??= test()->enrolment;
    app('auth')->forgetGuards();

    return test()->withHeaders([
        'Authorization' => 'Bearer '.$as->plainTextToken,
        'X-Device-ID' => $as->device->id,
    ])->postJson('/api/v1/shifts/sync', ['shifts' => $shifts]);
}

function shiftRow(string $uuid): Shift
{
    return test()->tenant->run(fn () => Shift::query()->findOrFail($uuid));
}

it('records an opened shift with its float, on the device’s branch', function () {
    $shift = shiftOf();

    syncShifts([$shift])->assertOk()->assertJsonPath('results.0', ['uuid' => $shift['uuid'], 'status' => 'accepted']);

    $row = shiftRow($shift['uuid']);
    expect($row->branch_id)->toBe($this->branch->id)
        ->and($row->device_id)->toBe($this->enrolment->device->id)
        ->and($row->float_cents)->toBe(500_000)
        ->and($row->isClosed())->toBeFalse()
        ->and($row->expected_cents)->toBeNull();
});

it('adds the cash that left the drawer as it is sent, and takes a resend as a duplicate', function () {
    $shift = shiftOf();
    syncShifts([$shift])->assertOk();

    $drop = movement(CashMovementKind::Drop, 200_000);
    syncShifts([[...$shift, 'movements' => [$drop]]])->assertJsonPath('results.0.status', 'updated');
    syncShifts([[...$shift, 'movements' => [$drop]]])->assertJsonPath('results.0.status', 'duplicate');
    syncShifts([$shift])->assertJsonPath('results.0.status', 'duplicate');

    expect($this->tenant->run(fn () => CashMovement::query()->sole()->only(['kind', 'amount_cents', 'shift_id'])))
        ->toBe(['kind' => CashMovementKind::Drop, 'amount_cents' => 200_000, 'shift_id' => $shift['uuid']]);
});

it('counts the drawer itself at close, from the synced sales and movements', function () {
    $shift = shiftOf();
    syncShifts([$shift])->assertOk();

    // Two sodas paid cash with KES 200 handed over, one M-Pesa sale: only the cash is in the drawer.
    $cashSale = saleOf([[$this->soda, 2]], 1, ['shift_id' => $shift['uuid'], 'tenders' => [cashTender(16_000, 20_000)]]);
    $mpesaSale = saleOf([[$this->soda, 1]], 2, ['shift_id' => $shift['uuid'], 'tenders' => [['method' => 'mpesa_code', 'amount_cents' => 8_000, 'mpesa_reference' => 'SKL1ABCDEF']]]);
    sync([$cashSale, $mpesaSale])->assertJsonPath('results.*.status', ['accepted', 'accepted']);

    // 5,000 float + 160 cash − 50 pay-out − 2,000 drop = 3,110; counted 3,100: KES 10 short.
    $close = closed(
        [...$shift, 'movements' => [movement(CashMovementKind::PayOut, 5_000), movement(CashMovementKind::Drop, 200_000)]],
        ['1000' => 3, '100' => 1],
        311_000,
    );
    syncShifts([$close])->assertJsonPath('results.0.status', 'updated');

    $row = shiftRow($shift['uuid']);
    expect($row->only(['counted_cents', 'till_expected_cents', 'cash_taken_cents', 'pay_outs_cents', 'drops_cents', 'void_refunds_cents', 'expected_cents', 'variance_cents']))
        ->toBe([
            'counted_cents' => 310_000,
            'till_expected_cents' => 311_000,
            'cash_taken_cents' => 16_000,
            'pay_outs_cents' => 5_000,
            'drops_cents' => 200_000,
            'void_refunds_cents' => 0,
            'expected_cents' => 311_000,
            'variance_cents' => -1_000,
        ])
        ->and($this->tenant->run(fn () => shiftRow($shift['uuid'])->reconciled_at))->not->toBeNull()
        ->and($row->closing_counts)->toEqual(['1000' => 3, '100' => 1])
        ->and(DomainEvent::query()->where('event_type', 'shift.closed')->sole()->aggregate_id)->toBe($shift['uuid']);
});

it('never takes the till’s expected figure: it keeps both, and counts again when a sale arrives late', function () {
    $shift = shiftOf();

    // The till believed one sale was in; the server has not seen it yet.
    syncShifts([closed($shift, ['1000' => 5, '100' => 1], 508_000)])->assertJsonPath('results.0.status', 'accepted');
    expect(shiftRow($shift['uuid'])->only(['till_expected_cents', 'expected_cents', 'variance_cents']))
        ->toBe(['till_expected_cents' => 508_000, 'expected_cents' => 500_000, 'variance_cents' => 10_000]);

    sync([saleOf([[$this->soda, 1]], 1, ['shift_id' => $shift['uuid']])])->assertJsonPath('results.0.status', 'accepted');

    expect(shiftRow($shift['uuid'])->only(['expected_cents', 'variance_cents']))->toBe(['expected_cents' => 508_000, 'variance_cents' => 2_000]);
});

it('includes a sale voided later, with its refund going out of the drawer that gave it back', function () {
    $shift = shiftOf();
    syncShifts([$shift])->assertOk();
    $sale = saleOf([[$this->soda, 1]], 1, ['shift_id' => $shift['uuid']]);
    sync([$sale])->assertOk();
    sync([voidOf($sale)])->assertJsonPath('results.0.status', 'updated');

    $refund = movement(CashMovementKind::VoidRefund, 8_000, ['order_uuid' => $sale['uuid']]);
    syncShifts([closed([...$shift, 'movements' => [$refund]], ['1000' => 4, '500' => 2], 500_000)])->assertOk();

    expect(shiftRow($shift['uuid'])->only(['cash_taken_cents', 'void_refunds_cents', 'expected_cents', 'variance_cents']))
        ->toBe(['cash_taken_cents' => 8_000, 'void_refunds_cents' => 8_000, 'expected_cents' => 500_000, 'variance_cents' => 0]);
});

it('keeps what was accepted: the opening, the close and no cash leaving after it', function () {
    $shift = shiftOf();
    $close = closed($shift, ['1000' => 5], 500_000);
    syncShifts([$close])->assertOk();

    syncShifts([[...$shift, 'number' => 2]])->assertJsonPath('results.0.reason', 'shift_conflict');
    syncShifts([[...$shift, 'opening_float' => ['counts' => ['1000' => 5]]]])->assertJsonPath('results.0.reason', 'shift_conflict');
    syncShifts([[...$close, 'expected_cents' => 1]])->assertJsonPath('results.0.reason', 'shift_conflict');
    syncShifts([[...$close, 'movements' => [movement(CashMovementKind::Drop, 100)]]])->assertJsonPath('results.0.reason', 'shift_closed');

    // A stale "open" or the same close again, from a retried batch, changes nothing.
    syncShifts([$shift, $close])->assertJsonPath('results.*.status', ['duplicate', 'duplicate']);
    // The same counts listed in another order are the same counts.
    syncShifts([[...$close, 'opening_float' => ['counts' => ['500' => 2, '1000' => 4, '50' => 0]]]])->assertJsonPath('results.0.status', 'duplicate');

    expect(DomainEvent::query()->where('event_type', 'shift.closed')->count())->toBe(1);
});

it('refuses a reused shift number, a movement of another shift and unknown staff', function () {
    $first = shiftOf();
    $drop = movement(CashMovementKind::Drop, 100);
    syncShifts([[...$first, 'movements' => [$drop]]])->assertOk();

    syncShifts([shiftOf()])->assertJsonPath('results.0.reason', 'number_conflict');
    syncShifts([shiftOf(['number' => 2, 'movements' => [$drop]])])->assertJsonPath('results.0.reason', 'movement_conflict');
    syncShifts([shiftOf(['number' => 3, 'opened_by' => (string) Str::uuid()])])->assertJsonPath('results.0.reason', 'unknown_staff');

    expect($this->tenant->run(fn () => Shift::query()->count()))->toBe(1);
});

it('refuses unknown fields at every level, and checks each shift on its own', function () {
    $good = shiftOf(['number' => 1]);
    $tenantInBody = shiftOf(['number' => 2, 'tenant_id' => 'tother']);
    $badCount = shiftOf(['number' => 3, 'opening_float' => ['counts' => ['1000' => 1], 'total_cents' => 100_000]]);
    $badMovement = shiftOf(['number' => 4, 'movements' => [movement(CashMovementKind::Drop, 100, ['branch_id' => (string) Str::uuid()])]]);

    $response = syncShifts([$good, $tenantInBody, $badCount, $badMovement])->assertOk();

    expect($response->json('results.*.status'))->toBe(['accepted', 'rejected', 'rejected', 'rejected'])
        ->and($response->json('results.1.errors'))->toHaveKey('tenant_id')
        ->and($response->json('results.2.errors'))->toHaveKey('opening_float.total_cents')
        ->and($response->json('results.3.errors'))->toHaveKey('movements.0.branch_id');
});

it('takes at most 50 shifts in a batch', function () {
    syncShifts(array_map(fn (int $n) => shiftOf(['number' => $n]), range(1, 51)))->assertUnprocessable()->assertJsonValidationErrors('shifts');
});

it('never shows or changes a shift across tills or businesses', function () {
    $shift = shiftOf();
    syncShifts([$shift])->assertOk();

    // Another till of the same branch cannot touch it.
    $otherTill = enrolFor($this->tenant, $this->branch);
    syncShifts([closed($shift, ['1000' => 1], 100_000)], $otherTill)->assertJsonPath('results.0.reason', 'uuid_conflict');

    // Another business: its own schema; the same UUID is its own shift, and ours is untouched.
    [$other, $otherBranch] = tenantWithBranch(BusinessType::Shop);
    $theirTill = enrolFor($other, $otherBranch);
    $theirs = [...$shift, 'opened_by' => sellerFor($other)];
    syncShifts([closed($theirs, ['1000' => 1], 500_000, ['closed_by' => sellerFor($other)])], $theirTill)->assertJsonPath('results.0.status', 'accepted');

    expect(shiftRow($shift['uuid'])->isClosed())->toBeFalse()
        ->and($other->run(fn () => Shift::query()->sole()->device_id))->toBe($theirTill->device->id);
});

it('records the shift a sale was made in, and still takes a sale with none', function () {
    $shift = shiftOf();
    $inShift = saleOf([[$this->soda, 1]], 1, ['shift_id' => $shift['uuid']]);
    $older = saleOf([[$this->soda, 1]], 2);

    sync([$inShift, $older])->assertJsonPath('results.*.status', ['accepted', 'accepted']);

    expect($this->tenant->run(fn () => Order::query()->orderBy('sequence_number')->pluck('shift_id')->all()))->toBe([$shift['uuid'], null]);
});
