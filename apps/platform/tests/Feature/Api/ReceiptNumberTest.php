<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Orders\Events\OrderRecorded;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Support\ReceiptNumber;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Event::fake([OrderRecorded::class]);

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->chai = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Chai', 'price_cents' => 5_000, 'vat_category' => VatCategory::Standard]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('numbers tills per business, starting again at T1 in another business', function () {
    $first = enrolFor($this->tenant, $this->branch)->device;
    $second = enrolFor($this->tenant, $this->branch)->device;

    [$otherTenant, $otherBranch] = tenantWithBranch();
    $elsewhere = enrolFor($otherTenant, $otherBranch)->device;

    expect([$first->tillCode(), $second->tillCode(), $elsewhere->tillCode()])->toBe(['T1', 'T2', 'T1']);
});

it('never gives a revoked till’s number to a new one', function () {
    enrolFor($this->tenant, $this->branch)->device;
    enrolFor($this->tenant, $this->branch)->device->revoke();

    expect(enrolFor($this->tenant, $this->branch)->device->tillCode())->toBe('T3');
});

it('tells the till its code', function () {
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    enrolFor($this->tenant, $this->branch);

    deviceRequest('GET', '/api/v1/device', $this->enrolment->plainTextToken, $this->enrolment->device->id)
        ->assertOk()
        ->assertJsonPath('device.till_code', 'T1');
});

it('keeps the receipt number of each synced sale, from the till that sent it', function () {
    enrolFor($this->tenant, $this->branch);
    $this->enrolment = enrolFor($this->tenant, $this->branch);

    $sale = saleOf([[$this->chai, 2]], sequence: 123);
    sync([$sale])->assertOk();

    $order = $this->tenant->run(fn () => Order::query()->findOrFail($sale['uuid']));
    $event = DomainEvent::query()->where('aggregate_id', $sale['uuid'])->sole();

    expect($order->receipt_number)->toBe('T2-000123')
        ->and($event->payload['receipt_number'])->toBe('T2-000123');
});

it('formats receipt numbers with at least six digits', function () {
    expect(ReceiptNumber::format(1, 1))->toBe('T1-000001')
        ->and(ReceiptNumber::format(12, 999_999))->toBe('T12-999999')
        ->and(ReceiptNumber::format(3, 1_234_567))->toBe('T3-1234567');
});

it('numbers a till created directly too, so no device is left without one', function () {
    $device = Device::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'name' => 'Bar']);

    expect($device->till_number)->toBe(1);
});
