<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Orders\Events\OrderRecorded;
use App\Domain\Orders\Events\OrderVoided;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\ReceiptReprint;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Owner\Pages\Receipts;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Event::fake([OrderRecorded::class, OrderVoided::class]);
    // The evening of the sales saleOf() makes (19:15 in Nairobi, 21 October).
    $this->travelTo('2026-10-21T17:00:00Z');

    [$this->tenant, $this->nyali] = tenantWithBranch();
    $this->westlands = $this->tenant->run(fn () => Branch::create(['name' => 'Westlands', 'business_type' => BusinessType::Restaurant]));

    $this->tenant->run(function () {
        $this->chai = Item::create(['type' => ItemType::Product, 'name' => 'Chai', 'price_cents' => 5_000, 'vat_category' => VatCategory::Standard]);
        $this->water = Item::create(['type' => ItemType::Product, 'name' => 'Water', 'price_cents' => 6_000, 'vat_category' => VatCategory::ZeroRated]);
        $this->owner = Staff::create(['name' => 'Owner Wanjiru', 'username' => 'owner', 'password' => 'correct-horse-9']);
        $this->owner->assignRole('owner');
        $this->manager = Staff::create(['name' => 'Manager Baraka', 'username' => 'manager', 'password' => 'correct-horse-9']);
        $this->manager->assignRole('manager');
        $this->manager->branches()->attach($this->nyali->id);
    });

    // T1 at Nyali sells for cash, T2 at Westlands takes a typed M-Pesa code.
    $this->nyaliTill = enrolFor($this->tenant, $this->nyali);
    $this->westlandsTill = enrolFor($this->tenant, $this->westlands);

    $this->enrolment = $this->nyaliTill;
    $this->cashSale = saleOf([[$this->chai, 2], [$this->water, 1]], sequence: 7);
    sync([$this->cashSale])->assertJsonPath('results.0.status', 'accepted');

    $this->enrolment = $this->westlandsTill;
    $mpesa = saleOf([[$this->chai, 1]], sequence: 3);
    $mpesa['tenders'] = [['method' => 'mpesa_code', 'amount_cents' => $mpesa['total_cents'], 'mpesa_reference' => 'TJL7QW2E9R']];
    $this->mpesaSale = $mpesa;
    sync([$mpesa])->assertJsonPath('results.0.status', 'accepted');
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function signInForReceipts(Tenant $tenant, Staff $staff): void
{
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    test()->actingAs($staff, 'tenant');
}

function receiptOf(string $uuid): Order
{
    return Order::query()->findOrFail($uuid);
}

it('lists every receipt for owners, and only their own branches for managers', function () {
    signInForReceipts($this->tenant, $this->owner);

    Livewire::test(Receipts::class)
        ->assertCanSeeTableRecords([receiptOf($this->cashSale['uuid']), receiptOf($this->mpesaSale['uuid'])])
        ->assertSee('T1-000007')
        ->assertSee('T2-000003')
        ->assertSee('M-Pesa TJL7QW2E9R');

    signInForReceipts($this->tenant, $this->manager);

    Livewire::test(Receipts::class)
        ->assertCanSeeTableRecords([receiptOf($this->cashSale['uuid'])])
        ->assertCanNotSeeTableRecords([receiptOf($this->mpesaSale['uuid'])])
        ->assertDontSee('T2-000003');
});

it('finds a receipt by the number on the slip or by its M-Pesa code', function () {
    signInForReceipts($this->tenant, $this->owner);
    $cash = receiptOf($this->cashSale['uuid']);
    $mpesa = receiptOf($this->mpesaSale['uuid']);

    Livewire::test(Receipts::class)
        ->searchTable('t1-000007')
        ->assertCanSeeTableRecords([$cash])->assertCanNotSeeTableRecords([$mpesa])
        ->searchTable('tjl7qw2e9r')
        ->assertCanSeeTableRecords([$mpesa])->assertCanNotSeeTableRecords([$cash]);
});

it('narrows by till, payment and status', function () {
    signInForReceipts($this->tenant, $this->owner);
    $cash = receiptOf($this->cashSale['uuid']);
    $mpesa = receiptOf($this->mpesaSale['uuid']);

    Livewire::test(Receipts::class)
        ->filterTable('device_id', $this->westlandsTill->device->id)
        ->assertCanSeeTableRecords([$mpesa])->assertCanNotSeeTableRecords([$cash])
        ->resetTableFilters()
        ->filterTable('paid_by', 'cash')
        ->assertCanSeeTableRecords([$cash])->assertCanNotSeeTableRecords([$mpesa])
        ->resetTableFilters()
        ->filterTable('status', 'voided')
        ->assertCanNotSeeTableRecords([$cash, $mpesa]);
});

it('shows only the period picked, today first', function () {
    $this->travelTo('2026-10-22T09:00:00Z');
    signInForReceipts($this->tenant, $this->owner);
    $cash = receiptOf($this->cashSale['uuid']);

    Livewire::test(Receipts::class)
        ->assertCanNotSeeTableRecords([$cash])
        ->filterTable('period', ['period' => 'yesterday'])
        ->assertCanSeeTableRecords([$cash]);
});

it('opens a receipt as printed, with its VAT and every reprint', function () {
    $cash = $this->tenant->run(fn () => receiptOf($this->cashSale['uuid']));
    $this->tenant->run(fn () => ReceiptReprint::query()->create([
        'id' => (string) Str::uuid7(), 'order_id' => $cash->id, 'device_id' => $this->nyaliTill->device->id,
        'staff_id' => $this->cashSale['staff_id'], 'reprinted_at' => '2026-10-21T16:40:00Z',
    ]));
    signInForReceipts($this->tenant, $this->manager);

    // Chai 2 × 50 standard-rated carries 13.79 VAT; the water is zero-rated.
    Livewire::test(Receipts::class)
        ->mountAction(TestAction::make('view')->table($cash))
        ->assertMountedActionModalSee([
            'T1-000007',
            '2 x Chai',
            __('owner.receipts.net'),
            'VAT 16% on KES 86.21',
            'KES 13.79',
            'VAT 0% on KES 60.00',
            'KES 160.00',
            __('owner.receipts.reprinted', ['time' => 'Wed 21 Oct 2026, 19:40', 'name' => 'Achieng Seller', 'till' => 'T1 · Till 1']),
        ]);
});

it('keeps each business’s receipts to itself', function () {
    [$other] = tenantWithBranch();
    $owner = $other->run(function () {
        $staff = Staff::create(['name' => 'Other owner', 'username' => 'owner', 'password' => 'correct-horse-9']);
        $staff->assignRole('owner');

        return $staff;
    });

    signInForReceipts($other, $owner);

    Livewire::test(Receipts::class)
        ->assertSee(__('owner.receipts.none'))
        ->assertDontSee('T1-000007')
        ->assertDontSee('TJL7QW2E9R');
});
