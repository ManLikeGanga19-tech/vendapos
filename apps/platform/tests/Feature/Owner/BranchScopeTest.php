<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\SummariseSalesDay;
use App\Domain\Analytics\Models\SalesHourly;
use App\Domain\Catalogue\Models\Item;
use App\Domain\Etims\Actions\CountOpenSubmissions;
use App\Domain\Etims\Actions\ListOpenSubmissions;
use App\Domain\Etims\Models\EtimsSubmission;
use App\Domain\Inventory\Actions\ListNegativeStock;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\EtimsDocumentType;
use VendaPOS\Core\Enums\EtimsStatus;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

const SCOPE_DAY = '2026-10-23';

beforeEach(function () {
    [$this->tenant, $this->nyali] = tenantWithBranch();
    $this->westlands = $this->tenant->run(fn () => Branch::create(['name' => 'Westlands', 'business_type' => BusinessType::Restaurant]));

    $this->tenant->run(function () {
        $item = Item::create(['type' => ItemType::Product, 'name' => 'Tusker', 'price_cents' => 30_000, 'vat_category' => VatCategory::Standard]);

        foreach ([[$this->nyali, 100_000, -2], [$this->westlands, 250_000, -5]] as [$branch, $total, $stock]) {
            SalesHourly::create([
                'branch_id' => $branch->id, 'business_date' => SCOPE_DAY, 'hour' => 13,
                'order_count' => 1, 'total_cents' => $total, 'vat_cents' => intdiv($total * 16, 116),
            ]);
            StockLevel::create(['branch_id' => $branch->id, 'item_id' => $item->id, 'quantity' => $stock]);
            EtimsSubmission::create([
                'order_id' => (string) Str::uuid(), 'branch_id' => $branch->id,
                'document_type' => EtimsDocumentType::Sale, 'status' => EtimsStatus::Pending, 'order_snapshot' => [],
            ]);
        }

        $this->owner = Staff::create(['name' => 'Owner', 'username' => 'owner', 'password' => 'correct-horse-9']);
        $this->owner->assignRole('owner');
        $this->manager = Staff::create(['name' => 'Manager', 'username' => 'manager', 'password' => 'correct-horse-9']);
        $this->manager->assignRole('manager');
        $this->manager->branches()->attach($this->nyali->id);
        $this->unassigned = Staff::create(['name' => 'New manager', 'username' => 'newmanager', 'password' => 'correct-horse-9']);
        $this->unassigned->assignRole('manager');
        $this->cashier = Staff::create(['name' => 'Cashier', 'username' => 'cashier', 'password' => 'correct-horse-9']);
        $this->cashier->assignRole('cashier');
        $this->cashier->branches()->attach($this->nyali->id);
    });

    [$this->other, $this->otherBranch] = tenantWithBranch();
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('lets owners see every branch and managers only their own', function () {
    $this->tenant->run(function () {
        expect($this->owner->visibleBranchIds())->toEqualCanonicalizing([$this->nyali->id, $this->westlands->id])
            ->and($this->manager->visibleBranchIds())->toBe([$this->nyali->id])
            ->and($this->unassigned->visibleBranchIds())->toBe([])
            ->and($this->cashier->visibleBranchIds())->toBe([]);
    });
});

it('limits sales, stock, eTIMS and branch lists to the given branches', function () {
    $this->tenant->run(function () {
        $mine = $this->manager->visibleBranchIds();

        expect(app(SummariseSalesDay::class)->handle(SCOPE_DAY, $mine)['total_cents'])->toBe(100_000)
            ->and(app(SummariseSalesDay::class)->handle(SCOPE_DAY)['total_cents'])->toBe(350_000)
            ->and(array_column(app(ListNegativeStock::class)->handle($mine), 'quantity'))->toBe([-2])
            ->and(array_column(app(ListOpenSubmissions::class)->handle(branchIds: $mine), 'branch_id'))->toBe([$this->nyali->id])
            ->and(app(CountOpenSubmissions::class)->handle($mine)['pending'])->toBe(1)
            ->and(array_keys(app(ListBranches::class)->handle($mine)))->toBe([$this->nyali->id]);
    });
});

it('shows a manager with no branches nothing at all, not everything', function () {
    $this->tenant->run(function () {
        $none = $this->unassigned->visibleBranchIds();

        expect(app(SummariseSalesDay::class)->handle(SCOPE_DAY, $none)['total_cents'])->toBe(0)
            ->and(app(ListNegativeStock::class)->handle($none))->toBe([])
            ->and(app(ListOpenSubmissions::class)->handle(branchIds: $none))->toBe([])
            ->and(app(CountOpenSubmissions::class)->handle($none))->toBe(['pending' => 0, 'failed' => 0])
            ->and(app(ListBranches::class)->handle($none))->toBe([]);
    });
});

it('never lists another tenant’s devices, even for a branch ID they share', function () {
    $nyaliTill = enrolFor($this->tenant, $this->nyali)->device;
    $westlandsTill = enrolFor($this->tenant, $this->westlands)->device;
    $otherTill = enrolFor($this->other, $this->otherBranch)->device;

    // Branch IDs are UUIDs, so a clash is not expected; prove the tenant filter holds anyway.
    $impostor = Device::create(['tenant_id' => $this->other->id, 'branch_id' => $this->nyali->id, 'name' => 'Impostor']);

    $owners = $this->tenant->run(fn () => $this->owner->visibleBranchIds());
    $managers = $this->tenant->run(fn () => $this->manager->visibleBranchIds());

    expect(Device::query()->ofTenantBranches($this->tenant->id, $owners)->pluck('id')->all())
        ->toEqualCanonicalizing([$nyaliTill->id, $westlandsTill->id])
        ->and(Device::query()->ofTenantBranches($this->tenant->id, $managers)->pluck('id')->all())->toBe([$nyaliTill->id])
        ->and(Device::query()->ofTenantBranches($this->tenant->id, [])->count())->toBe(0)
        ->and(Device::query()->ofTenantBranches($this->tenant->id, $owners)->pluck('id')->all())
        ->not->toContain($otherTill->id, $impostor->id);
});
