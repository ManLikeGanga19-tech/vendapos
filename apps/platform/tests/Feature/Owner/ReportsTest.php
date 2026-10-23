<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\SummariseSalesByDay;
use App\Domain\Analytics\Models\ItemSalesDaily;
use App\Domain\Analytics\Models\SalesHourly;
use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Owner\Pages\SalesReport;
use App\Filament\Owner\Pages\TopItems;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    // Friday 23 October 2026, mid-afternoon in Nairobi.
    $this->travelTo(now('Africa/Nairobi')->setDate(2026, 10, 23)->setTime(15, 0));

    [$this->tenant, $this->nyali] = tenantWithBranch();

    $this->tenant->run(function () {
        $this->westlands = Branch::create(['name' => 'Westlands', 'business_type' => BusinessType::Restaurant]);
        $this->tilapia = Item::create(['type' => ItemType::Product, 'name' => 'Tilapia', 'price_cents' => 70_000, 'vat_category' => VatCategory::Standard]);
        $this->ugali = Item::create(['type' => ItemType::Product, 'name' => 'Ugali', 'price_cents' => 10_000, 'vat_category' => VatCategory::Standard]);

        $hour = fn (Branch $branch, string $date, int $total, int $orders = 1) => SalesHourly::create([
            'branch_id' => $branch->id, 'business_date' => $date, 'hour' => 13, 'order_count' => $orders,
            'subtotal_cents' => $total - intdiv($total * 16, 116), 'vat_cents' => intdiv($total * 16, 116), 'total_cents' => $total,
        ]);
        $hour($this->nyali, '2026-10-23', 100_000);
        $hour($this->westlands, '2026-10-23', 250_000);
        $hour($this->nyali, '2026-10-21', 40_000, 2);
        $hour($this->nyali, '2026-10-01', 9_000); // this month, outside the last 7 days
        $hour($this->nyali, '2026-09-30', 7_000); // last month

        $sold = fn (Branch $branch, Item $item, string $date, int $quantity, int $total) => ItemSalesDaily::create([
            'branch_id' => $branch->id, 'business_date' => $date, 'item_id' => $item->id, 'quantity' => $quantity, 'total_cents' => $total,
        ]);
        $sold($this->nyali, $this->tilapia, '2026-10-23', 1, 70_000);
        $sold($this->nyali, $this->ugali, '2026-10-23', 3, 30_000);
        $sold($this->westlands, $this->tilapia, '2026-10-23', 3, 210_000);
        $sold($this->westlands, $this->ugali, '2026-10-23', 4, 40_000);

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

function signInForReports(Tenant $tenant, Staff $staff): void
{
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    test()->actingAs($staff, 'tenant');
}

/** @return array<string, mixed> */
function periodFilter(string $period, ?string $from = null, ?string $until = null, ?string $branch = null): array
{
    return array_filter([
        'period' => ['period' => $period, 'from' => $from, 'until' => $until],
        'branch' => $branch === null ? null : ['value' => $branch],
    ]);
}

it('returns every day in the range, newest first, with zeros for days without sales', function () {
    $this->tenant->run(function () {
        $days = app(SummariseSalesByDay::class)->handle('2026-10-20', '2026-10-23');

        expect(array_keys($days))->toBe(['2026-10-23', '2026-10-22', '2026-10-21', '2026-10-20'])
            ->and($days['2026-10-23']['total_cents'])->toBe(350_000)
            ->and($days['2026-10-22'])->toBe(['date' => '2026-10-22', 'orders' => 0, 'voids' => 0, 'cancels' => 0, 'subtotal_cents' => 0, 'vat_cents' => 0, 'total_cents' => 0])
            ->and($days['2026-10-21']['orders'])->toBe(2)
            ->and(app(SummariseSalesByDay::class)->handle('2026-10-23', '2026-10-23', [$this->nyali->id])['2026-10-23']['total_cents'])->toBe(100_000);
    });
});

it('refuses backwards ranges and ranges longer than the limit', function (string $from, string $to) {
    $this->tenant->run(fn () => app(SummariseSalesByDay::class)->handle($from, $to));
})->throws(InvalidArgumentException::class)->with([
    'backwards' => ['2026-10-23', '2026-10-22'],
    '93 days' => ['2026-07-23', '2026-10-23'],
]);

it('shows the owner each period’s days and totals', function (array $filters, array $dates, string $total) {
    signInForReports($this->tenant, $this->owner);

    $page = Livewire::test(SalesReport::class)->set('tableFilters', $filters);

    foreach ($dates as $date) {
        $page->assertSee($date);
    }
    $page->assertSee("Total {$total}");
})->with([
    'today' => [periodFilter('today'), ['Fri 23 Oct 2026'], 'KES 3,500.00'],
    'yesterday' => [periodFilter('yesterday'), ['Thu 22 Oct 2026'], 'KES 0.00'],
    'last 7 days' => [periodFilter('last_7_days'), ['Sat 17 Oct 2026', 'Wed 21 Oct 2026', 'Fri 23 Oct 2026'], 'KES 3,900.00'],
    'this month' => [periodFilter('this_month'), ['Thu 1 Oct 2026', 'Fri 23 Oct 2026'], 'KES 3,990.00'],
    'custom' => [periodFilter('custom', '2026-09-30', '2026-10-01'), ['Wed 30 Sep 2026', 'Thu 1 Oct 2026'], 'KES 160.00'],
]);

it('falls back to today for a range the browser should never have sent', function (array $filters) {
    signInForReports($this->tenant, $this->owner);

    Livewire::test(SalesReport::class)
        ->set('tableFilters', $filters)
        ->assertSee('Total KES 3,500.00')
        ->assertDontSee('Thu 22 Oct 2026');
})->with([
    'backwards' => [periodFilter('custom', '2026-10-23', '2026-10-01')],
    'too long' => [periodFilter('custom', '2026-01-01', '2026-10-23')],
    'not dates' => [periodFilter('custom', 'drop table', '<script>')],
    'impossible dates' => [periodFilter('custom', '2026-99-99', '2026-02-31')],
    'unknown period' => [periodFilter('forever')],
]);

it('stops a custom range at today', function () {
    signInForReports($this->tenant, $this->owner);

    Livewire::test(SalesReport::class)
        ->set('tableFilters', periodFilter('custom', '2026-10-22', '2026-12-31'))
        ->assertSee('Fri 23 Oct 2026')
        ->assertDontSee('Sat 24 Oct 2026');
});

it('lets the owner narrow reports to one branch', function () {
    signInForReports($this->tenant, $this->owner);

    Livewire::test(SalesReport::class)
        ->set('tableFilters', periodFilter('today', branch: $this->westlands->id))
        ->assertSee('Total KES 2,500.00');
});

it('keeps a manager’s reports to their own branch, whatever the filter says', function (?string $branch) {
    signInForReports($this->tenant, $this->manager);
    $picked = $branch === 'westlands' ? $this->westlands->id : $branch;

    Livewire::test(SalesReport::class)
        ->set('tableFilters', periodFilter('today', branch: $picked))
        ->assertSee('Total KES 1,000.00');

    Livewire::test(TopItems::class)
        ->set('tableFilters', periodFilter('today', branch: $picked))
        ->assertSee('KES 700.00')
        ->assertDontSee('KES 2,100.00');
})->with([
    'no pick' => [null],
    'another branch' => ['westlands'],
    'made-up branch' => ['not-a-branch'],
]);

it('ranks top items with each one’s share of all sales in the period', function () {
    signInForReports($this->tenant, $this->owner);

    // Tilapia KES 2,800 of KES 3,500 = 80.0%; ugali KES 700 = 20.0%.
    Livewire::test(TopItems::class)
        ->set('tableFilters', periodFilter('today'))
        ->assertSeeInOrder(['Tilapia', 'KES 2,800.00', '80.0%', 'Ugali', 'KES 700.00', '20.0%']);
});

it('covers this week from Monday and the whole of last month', function () {
    signInForReports($this->tenant, $this->owner);

    // Friday 23 October: the week began on Monday 19 October.
    Livewire::test(SalesReport::class)
        ->set('tableFilters', periodFilter('this_week'))
        ->assertSee('Mon 19 Oct 2026')
        ->assertDontSee('Sun 18 Oct 2026');

    Livewire::test(SalesReport::class)
        ->set('tableFilters', periodFilter('last_month'))
        ->assertSee('Tue 1 Sep 2026')
        ->assertSee('Wed 30 Sep 2026')
        ->assertSee('Total KES 70.00')
        ->assertDontSee('Thu 1 Oct 2026');
});

it('ranks top items by quantity when asked', function () {
    signInForReports($this->tenant, $this->owner);

    // Ugali sold 7 for KES 700; tilapia 4 for KES 2,800.
    Livewire::test(TopItems::class)
        ->set('tableFilters', periodFilter('today') + ['rank' => ['by' => 'quantity']])
        ->assertSeeInOrder(['Ugali', '7', 'Tilapia', '4'])
        ->assertSee(__('owner.reports.ranked_by_quantity'));
});

it('searches items by name in the database, ignoring case and wildcards', function () {
    signInForReports($this->tenant, $this->owner);

    Livewire::test(TopItems::class)
        ->set('tableFilters', periodFilter('today'))
        ->set('tableSearch', 'UGA')
        ->assertSee('Ugali')
        ->assertDontSee('Tilapia')
        ->set('tableSearch', '%')
        ->assertDontSee('Ugali')
        ->assertSee(__('owner.reports.no_match', ['search' => '%']));
});

it('starts a custom range from the period that was on screen', function () {
    signInForReports($this->tenant, $this->owner);

    $page = Livewire::test(SalesReport::class)
        ->set('tableFilters.period.period', 'last_7_days')
        ->set('tableFilters.period.period', 'custom');

    // The picker may add a time to its state; only the date matters.
    expect(substr((string) $page->get('tableFilters.period.from'), 0, 10))->toBe('2026-10-17')
        ->and(substr((string) $page->get('tableFilters.period.until'), 0, 10))->toBe('2026-10-23');
    $page->assertSee('Sat 17 Oct 2026')->assertSee('Fri 23 Oct 2026');
});
