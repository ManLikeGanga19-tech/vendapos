<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Orders\Events\OrderRecorded;
use App\Domain\Orders\Events\OrderVoided;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderVoid;
use App\Domain\Tenancy\Data\DeviceEnrolment;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\Enums\VoidReason;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Event::fake([OrderRecorded::class, OrderVoided::class]);

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->nyama = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Nyama choma', 'price_cents' => 185_000, 'vat_category' => VatCategory::Standard]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function voidRecordOf(string $orderId): ?OrderVoid
{
    return test()->tenant->run(fn () => OrderVoid::query()->find($orderId));
}

function orderStatusOf(string $orderId): string
{
    return test()->tenant->run(fn () => Order::query()->findOrFail($orderId)->status->value);
}

/** Someone at the test's tenant with $role, working at $branches. */
function staffWithRole(string $role, array $branches = []): string
{
    return test()->tenant->run(function () use ($role, $branches) {
        $staff = Staff::create(['name' => ucfirst($role).' '.Str::random(4)]);
        $staff->assignRole($role);
        $staff->branches()->attach(array_map(fn (Branch $branch) => $branch->id, $branches));

        return $staff->id;
    });
}

/** POST /orders/{id}/void as $as (another till of the branch, by default the test's own). */
function voidFromTill(string $orderId, array $void, ?DeviceEnrolment $as = null): TestResponse
{
    $as ??= test()->enrolment;
    app('auth')->forgetGuards();

    return test()->withHeaders([
        'Authorization' => 'Bearer '.$as->plainTextToken,
        'X-Device-ID' => $as->device->id,
    ])->postJson("/api/v1/orders/{$orderId}/void", $void);
}

it('records who voided a sale, who approved it and why', function () {
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);

    sync([voidOf($sale, ['reason' => 'other', 'note' => 'Customer walked out'])])->assertJsonPath('results.0.status', 'updated');

    $void = voidRecordOf($sale['uuid']);
    expect($void?->voided_by)->toBe($sale['staff_id'])
        ->and($void?->approved_by)->toBe(approverFor($this->tenant))
        ->and($void?->reason)->toBe(VoidReason::Other)
        ->and($void?->note)->toBe('Customer walked out')
        ->and($void?->device_id)->toBe($this->enrolment->device->id)
        ->and($this->tenant->run(fn () => OrderVoid::query()->findOrFail($sale['uuid'])->voided_at->toIso8601String()))->toBe('2026-10-21T16:25:00+00:00')
        ->and($void?->approver_allowed)->toBeTrue()
        ->and($void?->same_business_day)->toBeTrue();

    // Listeners read the void from the event, never from the Orders tables.
    $payload = $this->tenant->run(fn () => DomainEvent::query()->where('event_type', 'order.voided')->sole()->payload);
    expect($payload['void'])->toMatchArray(['reason' => 'other', 'approved_by' => approverFor($this->tenant), 'approver_allowed' => true]);
    Event::assertDispatchedTimes(OrderVoided::class, 1);
});

it('records the void of a sale rung up and voided before it synced', function () {
    $sale = saleOf([[$this->nyama, 1]], overrides: ['status' => 'voided']);

    sync([$sale])->assertJsonPath('results.0.status', 'accepted');

    expect(voidRecordOf($sale['uuid'])?->reason)->toBe(VoidReason::WrongItem)
        ->and($this->tenant->run(fn () => DomainEvent::query()->where('event_type', 'order.recorded')->sole()->payload['void']['voided_by']))->toBe($sale['staff_id']);
});

it('refuses a void without who, who approved and why, or with fields it does not know', function () {
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);

    sync([[...$sale, 'status' => 'voided']])
        ->assertJsonPath('results.0.reason', 'invalid')
        ->assertJsonPath('results.0.errors.void.0', fn (string $message) => $message !== '');

    $withExtra = voidOf($sale);
    $withExtra['void']['branch_id'] = (string) Str::uuid();
    sync([$withExtra])
        ->assertJsonPath('results.0.reason', 'invalid')
        ->assertJsonStructure(['results' => [['errors' => ['void.branch_id']]]]);

    expect(orderStatusOf($sale['uuid']))->toBe('completed')
        ->and(voidRecordOf($sale['uuid']))->toBeNull();
});

it('refuses a void by or approved by someone the business does not have', function () {
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);

    sync([voidOf($sale, ['approved_by' => (string) Str::uuid()])])
        ->assertJsonPath('results.0.reason', 'unknown_staff')
        ->assertJsonStructure(['results' => [['errors' => ['void.approved_by']]]]);

    expect(orderStatusOf($sale['uuid']))->toBe('completed');
    Event::assertNotDispatched(OrderVoided::class);
});

it('refuses a void dated before the sale', function () {
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);

    sync([voidOf($sale, ['voided_at' => '2026-10-21T19:00:00+03:00'])])
        ->assertJsonPath('results.0.reason', 'invalid')
        ->assertJsonStructure(['results' => [['errors' => ['void.voided_at']]]]);

    expect(orderStatusOf($sale['uuid']))->toBe('completed');
});

it('keeps a void whose approver could not approve it, and notes that for the owner', function (string $role, bool $worksHere, bool $allowed) {
    $otherBranch = $this->tenant->run(fn () => Branch::create(['name' => 'Bamburi', 'business_type' => BusinessType::Restaurant]));
    $approver = staffWithRole($role, $worksHere ? [$this->branch] : [$otherBranch]);
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);

    sync([voidOf($sale, ['approved_by' => $approver])])->assertJsonPath('results.0.status', 'updated');

    expect(orderStatusOf($sale['uuid']))->toBe('voided')
        ->and(voidRecordOf($sale['uuid'])?->approver_allowed)->toBe($allowed);
})->with([
    'a manager of this branch' => ['manager', true, true],
    'a manager of another branch' => ['manager', false, false],
    'a cashier of this branch' => ['cashier', true, false],
]);

it('notes a void made on a later business day than the sale (Nairobi time)', function () {
    $sale = saleOf([[$this->nyama, 1]], overrides: ['placed_at' => '2026-10-21T23:50:00+03:00']);
    sync([$sale]);

    sync([voidOf($sale, ['voided_at' => '2026-10-22T00:10:00+03:00'])])->assertJsonPath('results.0.status', 'updated');

    expect(voidRecordOf($sale['uuid'])?->same_business_day)->toBeFalse();
});

it('lets another till of the branch void a sale, once', function () {
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);
    $otherTill = enrolFor($this->tenant, $this->branch);
    $void = voidOf($sale)['void'];

    voidFromTill($sale['uuid'], $void, $otherTill)->assertOk()->assertExactJson(['uuid' => $sale['uuid'], 'status' => 'updated']);
    voidFromTill($sale['uuid'], $void, $otherTill)->assertOk()->assertJsonPath('status', 'duplicate');

    expect(voidRecordOf($sale['uuid'])?->device_id)->toBe($otherTill->device->id)
        ->and($this->tenant->run(fn () => DomainEvent::query()->where('event_type', 'order.voided')->count()))->toBe(1);
    Event::assertDispatchedTimes(OrderVoided::class, 1);

    // The till that made the sale learns of it late: its own resends change nothing.
    sync([$sale])->assertJsonPath('results.0.status', 'duplicate');
    sync([voidOf($sale)])->assertJsonPath('results.0.status', 'duplicate');
});

it('hides sales of other branches and other businesses from a till voiding one', function () {
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);
    $void = voidOf($sale)['void'];

    $otherBranch = $this->tenant->run(fn () => Branch::create(['name' => 'Bamburi', 'business_type' => BusinessType::Restaurant]));
    voidFromTill($sale['uuid'], $void, enrolFor($this->tenant, $otherBranch))->assertNotFound()->assertJsonPath('reason', 'order_not_found');

    [$otherTenant, $theirBranch] = tenantWithBranch();
    voidFromTill($sale['uuid'], $void, enrolFor($otherTenant, $theirBranch))->assertNotFound();

    expect(orderStatusOf($sale['uuid']))->toBe('completed')
        ->and(voidRecordOf($sale['uuid']))->toBeNull();
    Event::assertNotDispatched(OrderVoided::class);
});

it('refuses to void a sale that was never completed, one it does not have, or with fields it does not know', function () {
    $cancelled = saleOf([[$this->nyama, 1]], overrides: ['status' => 'cancelled']);
    $completed = saleOf([[$this->nyama, 1]], 2);
    sync([$cancelled, $completed]);
    $void = voidOf($completed)['void'];

    voidFromTill($cancelled['uuid'], $void)->assertConflict()->assertJsonPath('reason', 'status_conflict');
    voidFromTill((string) Str::uuid(), $void)->assertNotFound();
    voidFromTill($completed['uuid'], [...$void, 'status' => 'voided'])->assertUnprocessable()->assertJsonValidationErrors('status');
    voidFromTill($completed['uuid'], [...$void, 'reason' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('note');
    voidFromTill($completed['uuid'], [...$void, 'voided_by' => (string) Str::uuid()])->assertUnprocessable()->assertJsonPath('reason', 'unknown_staff');

    expect(orderStatusOf($completed['uuid']))->toBe('completed');
    Event::assertNotDispatched(OrderVoided::class);
});
