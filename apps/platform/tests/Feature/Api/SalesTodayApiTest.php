<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Orders\Events\OrderRecorded;
use App\Domain\Orders\Events\OrderVoided;
use App\Domain\Tenancy\Data\DeviceEnrolment;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Event::fake([OrderRecorded::class, OrderVoided::class]);
    // 21 October, 20:00 in Nairobi.
    $this->travelTo('2026-10-21T17:00:00Z');

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->chai = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Chai', 'price_cents' => 5_000, 'vat_category' => VatCategory::Standard]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function salesToday(?DeviceEnrolment $as = null): TestResponse
{
    $as ??= test()->enrolment;
    app('auth')->forgetGuards();

    return test()->withHeaders([
        'Authorization' => 'Bearer '.$as->plainTextToken,
        'X-Device-ID' => $as->device->id,
    ])->getJson('/api/v1/orders/today');
}

it('lists the branch’s sales today from every till, newest first, with any void', function () {
    $mine = saleOf([[$this->chai, 1]], 1, ['placed_at' => '2026-10-21T09:00:00+03:00']);
    sync([$mine]);
    sync([voidOf($mine, ['reason' => 'duplicate'])]);

    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $theirs = saleOf([[$this->chai, 2]], 1, ['placed_at' => '2026-10-21T19:30:00+03:00']);
    $theirs['tenders'] = [['method' => 'mpesa_code', 'amount_cents' => 10_000, 'mpesa_reference' => 'TJL7QW2E9R']];
    sync([$theirs]);

    $orders = salesToday()->assertOk()->json('orders');

    expect(array_column($orders, 'id'))->toBe([$theirs['uuid'], $mine['uuid']])
        ->and($orders[0])->toMatchArray(['receipt_number' => 'T2-000001', 'status' => 'completed', 'total_cents' => 10_000, 'void' => null])
        ->and($orders[0]['tenders'])->toBe([['method' => 'mpesa_code', 'amount_cents' => 10_000, 'mpesa_reference' => 'TJL7QW2E9R']])
        ->and($orders[0]['lines'][0])->toMatchArray(['name' => 'Chai', 'quantity' => 2])
        ->and($orders[1]['status'])->toBe('voided')
        ->and($orders[1]['void'])->toMatchArray(['reason' => 'duplicate', 'approved_by' => approverFor($this->tenant)]);
});

it('leaves out yesterday (Nairobi time) and sales that were cancelled', function () {
    sync([
        saleOf([[$this->chai, 1]], 1, ['placed_at' => '2026-10-20T23:59:00+03:00']),
        saleOf([[$this->chai, 1]], 2, ['placed_at' => '2026-10-21T00:01:00+03:00']),
        saleOf([[$this->chai, 1]], 3, ['status' => 'cancelled']),
    ]);

    expect(array_column(salesToday()->json('orders'), 'receipt_number'))->toBe(['T1-000002']);
});

it('shows a till only its own branch’s sales, never another business’s', function () {
    sync([saleOf([[$this->chai, 1]])]);

    $otherBranch = $this->tenant->run(fn () => Branch::create(['name' => 'Bamburi', 'business_type' => BusinessType::Restaurant]));
    salesToday(enrolFor($this->tenant, $otherBranch))->assertOk()->assertExactJson(['orders' => []]);

    [$otherTenant, $theirBranch] = tenantWithBranch();
    salesToday(enrolFor($otherTenant, $theirBranch))->assertOk()->assertExactJson(['orders' => []]);
});
