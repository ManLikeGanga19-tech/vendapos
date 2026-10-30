<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Orders\Events\OrderRecorded;
use App\Domain\Orders\Models\ReceiptReprint;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Event::fake([OrderRecorded::class]);

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->chai = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Chai', 'price_cents' => 5_000, 'vat_category' => VatCategory::Standard]));

    $this->sale = saleOf([[$this->chai, 1]]);
    sync([$this->sale])->assertOk();
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** @return array<string, mixed> */
function reprintOf(string $orderUuid, array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'order_uuid' => $orderUuid,
        'staff_id' => sellerFor(saleTenant()),
        'reprinted_at' => '2026-10-30T14:05:00+03:00',
    ], $overrides);
}

function syncReprints(array $reprints): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withHeaders([
        'Authorization' => 'Bearer '.test()->enrolment->plainTextToken,
        'X-Device-ID' => test()->enrolment->device->id,
    ])->postJson('/api/v1/receipts/reprints/sync', ['reprints' => $reprints]);
}

it('keeps who reprinted a receipt and when, once however often it is sent', function () {
    $reprint = reprintOf($this->sale['uuid']);

    syncReprints([$reprint])->assertOk()->assertExactJson(['results' => [['uuid' => $reprint['uuid'], 'status' => 'accepted']]]);
    syncReprints([$reprint])->assertOk()->assertJsonPath('results.0.status', 'duplicate');

    // Read inside the tenant: the date cast needs the model's connection.
    $kept = $this->tenant->run(fn () => ReceiptReprint::query()->sole()->only(['order_id', 'device_id', 'staff_id']) + [
        'reprinted_at' => ReceiptReprint::query()->sole()->reprinted_at->toIso8601String(),
    ]);

    expect($kept)->toBe([
        'order_id' => $this->sale['uuid'],
        'device_id' => $this->enrolment->device->id,
        'staff_id' => $reprint['staff_id'],
        'reprinted_at' => '2026-10-30T11:05:00+00:00',
    ]);
});

it('refuses reprints of sales this till never recorded, including another till’s and another business’s', function () {
    $ownSale = $this->sale['uuid'];

    // Another till in the same business.
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    syncReprints([reprintOf($ownSale)])->assertOk()->assertJsonPath('results.0.reason', 'unknown_order');

    // Another business entirely.
    [$otherTenant, $otherBranch] = tenantWithBranch();
    $this->enrolment = enrolFor($otherTenant, $otherBranch);
    syncReprints([reprintOf($ownSale)])->assertOk()->assertJsonPath('results.0.reason', 'unknown_order');

    syncReprints([reprintOf((string) Str::uuid())])->assertOk()->assertJsonPath('results.0.reason', 'unknown_order');

    expect($this->tenant->run(fn () => ReceiptReprint::query()->count()))->toBe(0)
        ->and($otherTenant->run(fn () => ReceiptReprint::query()->count()))->toBe(0);
});

it('refuses staff of no one at this business', function () {
    [$otherTenant] = tenantWithBranch();

    syncReprints([reprintOf($this->sale['uuid'], ['staff_id' => sellerFor($otherTenant)])])
        ->assertOk()
        ->assertJsonPath('results.0.reason', 'unknown_staff');
});

it('rejects unknown fields and bad entries one by one, without failing the batch', function () {
    $good = reprintOf($this->sale['uuid']);

    syncReprints([reprintOf($this->sale['uuid'], ['branch_id' => (string) Str::uuid()]), ['uuid' => 'nope'], $good])
        ->assertOk()
        ->assertJsonPath('results.0.reason', 'invalid')
        ->assertJsonPath('results.0.errors.branch_id.0', 'Unknown field.')
        ->assertJsonPath('results.1.reason', 'invalid')
        ->assertJsonPath('results.2.status', 'accepted');
});

it('limits batches to 50 and needs the orders:sync ability', function () {
    syncReprints(array_fill(0, 51, reprintOf($this->sale['uuid'])))->assertUnprocessable()->assertJsonValidationErrors('reprints');

    $token = $this->enrolment->device->createToken('read-only', ['catalogue:read'], now()->addHour())->plainTextToken;
    app('auth')->forgetGuards();

    $this->withHeaders(['Authorization' => "Bearer {$token}", 'X-Device-ID' => $this->enrolment->device->id])
        ->postJson('/api/v1/receipts/reprints/sync', ['reprints' => [reprintOf($this->sale['uuid'])]])
        ->assertForbidden();
});
