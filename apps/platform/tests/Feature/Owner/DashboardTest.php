<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\ListTopItems;
use App\Domain\Analytics\Models\ItemSalesDaily;
use App\Domain\Analytics\Models\SalesHourly;
use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Owner\Pages\Dashboard;
use App\Filament\Owner\Widgets\SalesByHour;
use App\Filament\Owner\Widgets\TodayStats;
use App\Filament\Owner\Widgets\TopItemsToday;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    $this->travelTo(now('Africa/Nairobi')->setTime(15, 0));
    $this->today = now('Africa/Nairobi')->toDateString();

    [$this->tenant, $this->nyali] = tenantWithBranch();
    $this->tenant->update(['name' => 'Mama Oliech Restaurant']);
    $this->tenant->domains()->create(['domain' => 'mama.localhost']);

    $this->tenant->run(function () {
        $this->westlands = Branch::create(['name' => 'Westlands', 'business_type' => BusinessType::Restaurant]);
        $item = fn (string $name) => Item::create(['type' => ItemType::Product, 'name' => $name, 'price_cents' => 10_000, 'vat_category' => VatCategory::Standard]);
        $this->tilapia = $item('Tilapia');
        $this->ugali = $item('Ugali');
        $this->soda = $item('Soda');

        // Nyali sold KES 1,000 at 13:00; Westlands KES 2,500 at 19:00.
        foreach ([[$this->nyali, 13, 100_000], [$this->westlands, 19, 250_000]] as [$branch, $hour, $total]) {
            SalesHourly::create(['branch_id' => $branch->id, 'business_date' => $this->today, 'hour' => $hour, 'order_count' => 1, 'total_cents' => $total]);
        }

        $sold = fn (Branch $branch, Item $item, int $quantity, int $total, ?string $date = null) => ItemSalesDaily::create([
            'branch_id' => $branch->id, 'business_date' => $date ?? $this->today, 'item_id' => $item->id, 'quantity' => $quantity, 'total_cents' => $total,
        ]);
        $sold($this->nyali, $this->tilapia, 1, 70_000);
        $sold($this->nyali, $this->ugali, 3, 30_000);
        $sold($this->westlands, $this->ugali, 5, 50_000);
        $sold($this->westlands, $this->tilapia, 2, 200_000);
        $sold($this->westlands, $this->soda, 0, 0); // sold, then voided
        $sold($this->nyali, $this->soda, 9, 90_000, now('Africa/Nairobi')->subDay()->toDateString());

        $this->owner = Staff::create(['name' => 'Owner', 'username' => 'owner', 'password' => 'correct-horse-9']);
        $this->owner->assignRole('owner');
        $this->manager = Staff::create(['name' => 'Manager', 'username' => 'manager', 'password' => 'correct-horse-9']);
        $this->manager->assignRole('manager');
        $this->manager->branches()->attach($this->nyali->id);
    });
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** Signs a staff member in to the Owner panel inside their tenant (Livewire tests skip HTTP middleware). */
function signInToOwnerPanel(Tenant $tenant, Staff $staff): void
{
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    test()->actingAs($staff, 'tenant');
}

it('ranks items by revenue over the business days asked for, net of voids', function () {
    $this->tenant->run(function () {
        $top = app(ListTopItems::class)->handle($this->today, $this->today);

        expect($top)->toBe([
            ['item_id' => $this->tilapia->id, 'quantity' => 3, 'total_cents' => 270_000],
            ['item_id' => $this->ugali->id, 'quantity' => 8, 'total_cents' => 80_000],
        ])
            ->and(array_column(app(ListTopItems::class)->handle($this->today, $this->today, [$this->nyali->id]), 'total_cents'))->toBe([70_000, 30_000])
            ->and(app(ListTopItems::class)->handle($this->today, $this->today, limit: 1))->toHaveCount(1);
    });
});

it('shows the owner today across every branch', function () {
    signInToOwnerPanel($this->tenant, $this->owner);

    Livewire::test(TodayStats::class)->assertSee('KES 3,500.00')->assertSee('2');
    Livewire::test(TopItemsToday::class)->assertSeeInOrder(['Tilapia', 'KES 2,700.00', 'Ugali', 'KES 800.00'])->assertDontSee('Soda');
    Livewire::test(SalesByHour::class)->assertSee('13:00 KES 1,000; 19:00 KES 2,500');
});

it('narrows the owner’s dashboard to the branch they pick', function () {
    signInToOwnerPanel($this->tenant, $this->owner);
    $westlands = ['branch' => $this->westlands->id];

    Livewire::test(TodayStats::class, ['pageFilters' => $westlands])->assertSee('KES 2,500.00')->assertDontSee('KES 3,500.00');
    Livewire::test(SalesByHour::class, ['pageFilters' => $westlands])->assertSee('19:00 KES 2,500')->assertDontSee('13:00 KES');
});

it('shows a manager only their own branch, even when the picker names another', function (?string $picked) {
    signInToOwnerPanel($this->tenant, $this->manager);
    $filters = ['branch' => $picked === 'westlands' ? $this->westlands->id : $picked];

    Livewire::test(TodayStats::class, ['pageFilters' => $filters])->assertSee('KES 1,000.00')->assertDontSee('KES 2,500.00')->assertDontSee('KES 3,500.00');
    Livewire::test(TopItemsToday::class, ['pageFilters' => $filters])->assertSee('KES 700.00')->assertDontSee('KES 2,700.00');
    Livewire::test(SalesByHour::class, ['pageFilters' => $filters])->assertSee('13:00 KES 1,000')->assertDontSee('19:00 KES');
})->with([
    'no pick' => [null],
    'another branch' => ['westlands'],
    'made-up branch' => ['not-a-branch'],
]);

it('offers the branch picker only with branches to choose between', function () {
    signInToOwnerPanel($this->tenant, $this->owner);
    Livewire::test(Dashboard::class)->assertSee('Westlands')->assertSee(__('owner.dashboard.all_branches'));

    $this->actingAs($this->manager, 'tenant');
    Livewire::test(Dashboard::class)->assertDontSee('Westlands')->assertDontSee(__('owner.dashboard.all_branches'));
});

it('heads the panel with the business’s own name', function () {
    $this->tenant->run(fn () => $this->owner->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret()));

    $this->actingAs($this->owner, 'tenant')
        ->get('http://mama.localhost/')
        ->assertOk()
        ->assertSee('Mama Oliech Restaurant');
});
