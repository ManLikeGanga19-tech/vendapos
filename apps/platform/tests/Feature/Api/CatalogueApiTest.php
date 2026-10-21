<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Category;
use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** @param array<string, mixed> $attributes */
function addItem(Tenant $tenant, array $attributes): Item
{
    return $tenant->run(fn () => Item::create([
        'type' => ItemType::Product,
        'vat_category' => VatCategory::Standard,
        'price_cents' => 35_000,
        ...$attributes,
    ]));
}

it('returns the active menu snapshot for the device’s tenant', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);
    $drinks = $tenant->run(fn () => Category::create(['name' => 'Drinks', 'sort_order' => 1]));
    addItem($tenant, ['name' => 'Tusker 500 ml', 'category_id' => $drinks->id, 'barcode' => '6161101560016']);
    addItem($tenant, ['name' => 'Discontinued soda', 'is_active' => false]);

    deviceRequest('GET', '/api/v1/catalogue', $enrolment->plainTextToken, $enrolment->device->id)
        ->assertOk()
        ->assertJsonPath('full', true)
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.name', 'Tusker 500 ml')
        ->assertJsonPath('items.0.price_cents', 35_000)
        ->assertJsonPath('items.0.vat_category', 'standard')
        ->assertJsonPath('items.0.category_id', $drinks->id)
        ->assertJsonPath('categories.0.name', 'Drinks');
});

it('never shows one tenant’s catalogue to another tenant’s device', function () {
    [$tenantA, $branchA] = tenantWithBranch();
    [$tenantB] = tenantWithBranch();
    $enrolment = enrolFor($tenantA, $branchA);
    addItem($tenantA, ['name' => 'Item of A']);
    addItem($tenantB, ['name' => 'Item of B']);

    $names = deviceRequest('GET', '/api/v1/catalogue', $enrolment->plainTextToken, $enrolment->device->id)
        ->assertOk()
        ->json('items.*.name');

    expect($names)->toBe(['Item of A']);
});

it('returns only changes since the last sync, including deactivated items', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);
    $unchanged = addItem($tenant, ['name' => 'Chapati']);
    $repriced = addItem($tenant, ['name' => 'Mandazi']);
    $dropped = addItem($tenant, ['name' => 'Samosa']);

    $syncedAt = deviceRequest('GET', '/api/v1/catalogue', $enrolment->plainTextToken, $enrolment->device->id)->json('synced_at');

    $this->travel(5)->minutes();
    $tenant->run(function () use ($repriced, $dropped) {
        $repriced->update(['price_cents' => 3_000]);
        $dropped->update(['is_active' => false]);
    });

    $response = deviceRequest('GET', '/api/v1/catalogue?updated_since='.urlencode($syncedAt), $enrolment->plainTextToken, $enrolment->device->id)
        ->assertOk()
        ->assertJsonPath('full', false);

    expect($response->json('items.*.name'))->toBe(['Mandazi', 'Samosa'])
        ->and($response->json('items.1.is_active'))->toBeFalse()
        ->and($response->json('items.*.id'))->not->toContain($unchanged->id);
});

it('rejects unknown and invalid query parameters', function () {
    [$tenant, $branch] = tenantWithBranch();
    $enrolment = enrolFor($tenant, $branch);

    deviceRequest('GET', '/api/v1/catalogue?updated_since=yesterday-ish&tenant_id=x', $enrolment->plainTextToken, $enrolment->device->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['updated_since', 'tenant_id']);
});
