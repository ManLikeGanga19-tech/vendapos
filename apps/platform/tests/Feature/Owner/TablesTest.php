<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Verticals\Actions\ManageFloor;
use App\Domain\Verticals\Exceptions\FloorChangeRefused;
use App\Domain\Verticals\Models\DiningTable;
use App\Domain\Verticals\Models\FloorArea;
use App\Filament\Owner\Pages\Tables;
use App\Infrastructure\Realtime\TillsShouldSync;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use VendaPOS\Core\Enums\BusinessType;

uses(DatabaseMigrations::class);

beforeEach(function () {
    [$this->tenant, $this->nyali] = tenantWithBranch(BusinessType::Restaurant);

    $this->tenant->run(function () {
        $this->bar = Branch::create(['name' => 'Westlands Bar', 'business_type' => BusinessType::Bar]);
        $this->shop = Branch::create(['name' => 'Duka', 'business_type' => BusinessType::Shop]);

        $make = function (string $username, string $role, array $branches = []): Staff {
            $staff = Staff::create(['name' => ucfirst($username), 'username' => $username, 'password' => 'correct-horse-9']);
            $staff->assignRole($role);
            $staff->branches()->attach(array_map(fn (Branch $b) => $b->id, $branches));

            return $staff;
        };

        $this->owner = $make('owner', 'owner');
        $this->nyaliManager = $make('nyali', 'manager', [$this->nyali]);
        $this->shopManager = $make('duka', 'manager', [$this->shop]);
    });
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function asTablesUser(Staff $staff): void
{
    tenancy()->initialize(test()->tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    test()->actingAs($staff, 'tenant');
}

it('reads table names from ranges and lists', function (string $input, array $names) {
    expect(ManageFloor::namesFrom($input))->toBe($names);
})->with([
    'numbers' => ['1-4', ['1', '2', '3', '4']],
    'prefixed' => ['T9-T11', ['T9', 'T10', 'T11']],
    'prefixed with a space' => ['Bar 1-Bar 3', ['Bar 1', 'Bar 2', 'Bar 3']],
    'list' => ['VIP, Bar 1, VIP', ['VIP', 'Bar 1']],
    'backwards' => ['12-1', []],
    'too many' => ['1-101', []],
    'a name too long' => ['The very long corner table by the window', []],
]);

it('lets the owner lay out a floor from the page, and tells that branch’s tills', function () {
    Event::fake([TillsShouldSync::class]);
    enrolFor($this->tenant, $this->nyali);
    asTablesUser($this->owner);

    Livewire::test(Tables::class)
        ->callAction(TestAction::make('addArea')->table(), ['branch_id' => $this->nyali->id, 'name' => 'Main hall'])
        ->assertHasNoActionErrors();

    $area = FloorArea::query()->sole();

    Livewire::test(Tables::class)
        ->callAction(TestAction::make('addTables')->table(), ['floor_area_id' => $area->id, 'names' => '1-6', 'seats' => 4])
        ->assertHasNoActionErrors()
        ->assertSee('Main hall');

    expect(DiningTable::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['1', '2', '3', '4', '5', '6']);
    Event::assertDispatched(TillsShouldSync::class, fn (TillsShouldSync $event) => $event->what === TillsShouldSync::TABLES && $event->deviceIds !== []);
});

it('refuses a name the branch already uses, and removing an area that still has tables', function () {
    $this->tenant->run(function () {
        $floor = app(ManageFloor::class);
        $area = $floor->saveArea(null, $this->nyali->id, 'Main hall');
        $floor->addTables($area->id, '1-3', 4);

        expect(fn () => $floor->addTables($area->id, '3-5', 4))->toThrow(FloorChangeRefused::class, 'name_taken')
            ->and(fn () => $floor->saveArea(null, $this->nyali->id, 'Main hall'))->toThrow(FloorChangeRefused::class, 'name_taken')
            ->and(fn () => $floor->deleteArea($area->id))->toThrow(FloorChangeRefused::class, 'area_not_empty');

        // Another branch may use the same names.
        $floor->addTables($floor->saveArea(null, $this->bar->id, 'Main hall')->id, '1-3', 2);
        expect(DiningTable::query()->count())->toBe(6);
    });
});

it('lets managers lay out only their own branches, and hides the page from shops', function () {
    asTablesUser($this->nyaliManager);

    Livewire::test(Tables::class)
        ->callAction(TestAction::make('addArea')->table(), ['branch_id' => $this->bar->id, 'name' => 'Sneaky'])
        ->assertHasActionErrors(['branch_id']);

    expect(FloorArea::query()->count())->toBe(0);

    asTablesUser($this->shopManager);
    Livewire::test(Tables::class)->assertForbidden();
});

it('gives each till its own branch’s tables in use, and nothing from other branches or businesses', function () {
    $this->tenant->run(function () {
        $floor = app(ManageFloor::class);
        $tables = $floor->addTables($floor->saveArea(null, $this->nyali->id, 'Terrace')->id, 'T1-T3', 2);
        $floor->setTableActive($tables[2]->id, false);
        $floor->addTables($floor->saveArea(null, $this->bar->id, 'Counter')->id, 'Bar 1-Bar 2', 1);
    });

    [$other, $otherBranch] = tenantWithBranch(BusinessType::Restaurant);
    $other->run(fn () => app(ManageFloor::class)->addTables(app(ManageFloor::class)->saveArea(null, $otherBranch->id, 'Theirs')->id, '90-91', 2));

    $this->enrolment = enrolFor($this->tenant, $this->nyali);

    deviceRequest('GET', '/api/v1/floor', $this->enrolment->plainTextToken, $this->enrolment->device->id)
        ->assertOk()
        ->assertJsonCount(1, 'areas')
        ->assertJsonPath('areas.0.name', 'Terrace')
        ->assertJsonPath('areas.0.tables.*.name', ['T1', 'T2']);
});
