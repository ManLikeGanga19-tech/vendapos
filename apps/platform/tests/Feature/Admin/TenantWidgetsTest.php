<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Resources\TenantResource\Widgets\TenantSalesByHour;
use App\Filament\Resources\TenantResource\Widgets\TenantToday;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('platform-admin');

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->nyama = $this->tenant->run(fn () => Item::create([
        'type' => ItemType::Product, 'name' => 'Nyama choma', 'price_cents' => 185_000, 'vat_category' => VatCategory::Standard,
    ]));

    // 19:15 in Nairobi on the 22nd.
    $this->travelTo(Carbon::parse('2026-10-22T16:15:00Z'));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('shows today’s sales, orders, voids and cancellations from the rollups', function () {
    sync([
        saleOf([[$this->nyama, 1]], sequence: 1, overrides: ['placed_at' => '2026-10-22T19:05:00+03:00']),
        saleOf([[$this->nyama, 1]], sequence: 2, overrides: ['placed_at' => '2026-10-22T19:10:00+03:00', 'status' => 'cancelled']),
        // Yesterday: not today's figures.
        saleOf([[$this->nyama, 2]], sequence: 3, overrides: ['placed_at' => '2026-10-21T23:30:00+03:00']),
    ]);
    $this->actingAs($this->admin);

    Livewire::test(TenantToday::class, ['record' => $this->tenant])
        ->assertSee('KES 1,850.00')
        ->assertSee('Including KES 255.17 VAT')
        ->assertSee('0 / 1')
        ->assertDontSee('KES 5,550.00');

    expect(tenancy()->initialized)->toBeFalse();
});

it('charts today’s sales per Nairobi hour, with a text version', function () {
    sync([
        saleOf([[$this->nyama, 1]], sequence: 1, overrides: ['placed_at' => '2026-10-22T08:40:00+03:00']),
        saleOf([[$this->nyama, 2]], sequence: 2, overrides: ['placed_at' => '2026-10-22T16:05:00Z']),
    ]);
    $this->actingAs($this->admin);

    $widget = Livewire::test(TenantSalesByHour::class, ['record' => $this->tenant])->instance();

    expect($widget->getChartAssistiveContent())->toBe('08:00 KES 1,850; 19:00 KES 3,700');
});

it('never shows another tenant’s figures', function () {
    sync([saleOf([[$this->nyama, 1]], overrides: ['placed_at' => '2026-10-22T19:05:00+03:00'])]);
    [$other] = tenantWithBranch();
    $this->actingAs($this->admin);

    Livewire::test(TenantToday::class, ['record' => $other])
        ->assertSee('KES 0.00')
        ->assertDontSee('KES 1,850.00');

    expect(Livewire::test(TenantSalesByHour::class, ['record' => $other])->instance()->getChartAssistiveContent())
        ->toBe('No sales yet today.');
});
