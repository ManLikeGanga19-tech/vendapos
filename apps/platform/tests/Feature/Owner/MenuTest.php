<?php

declare(strict_types=1);

use App\Domain\Catalogue\Actions\DeleteCategory;
use App\Domain\Catalogue\Actions\ListMenuItems;
use App\Domain\Catalogue\Actions\SaveCategory;
use App\Domain\Catalogue\Actions\SaveMenuItem;
use App\Domain\Catalogue\Actions\SetMenuItemOnSale;
use App\Domain\Catalogue\Exceptions\MenuConflict;
use App\Domain\Catalogue\Models\Category;
use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Owner\Pages\MenuCategories;
use App\Filament\Owner\Pages\MenuItems;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\ValueObjects\Money;

uses(DatabaseMigrations::class);

beforeEach(function () {
    [$this->tenant, $this->branch] = tenantWithBranch();

    $this->tenant->run(function () {
        $this->drinks = Category::create(['name' => 'Drinks', 'sort_order' => 1]);
        $this->soda = Item::create(['category_id' => $this->drinks->id, 'type' => ItemType::Product, 'name' => 'Soda', 'barcode' => '6001', 'price_cents' => 8_000, 'vat_category' => VatCategory::Standard]);

        $this->owner = Staff::create(['name' => 'Owner', 'username' => 'owner', 'password' => 'correct-horse-9']);
        $this->owner->assignRole('owner');
        $this->manager = Staff::create(['name' => 'Manager', 'username' => 'manager', 'password' => 'correct-horse-9']);
        $this->manager->assignRole('manager');
        $this->manager->branches()->attach($this->branch->id);
    });
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function signInToMenu(Tenant $tenant, Staff $staff): void
{
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    test()->actingAs($staff, 'tenant');
}

/** @param array<string, mixed> $overrides */
function saveItem(array $overrides = []): string
{
    return app(SaveMenuItem::class)->handle(...[
        'id' => null,
        'name' => 'Chapati',
        'type' => ItemType::Product,
        'price' => Money::fromCents(3_000),
        'vatCategory' => VatCategory::Standard,
        ...$overrides,
    ]);
}

it('adds and changes items, keeping the type it was added with', function () {
    $this->tenant->run(function () {
        $id = saveItem(['categoryId' => $this->drinks->id, 'sku' => ' CH-1 ', 'barcode' => '']);
        saveItem(['id' => $id, 'name' => 'Chapati (2)', 'type' => ItemType::Service, 'price' => Money::fromCents(5_000), 'vatCategory' => VatCategory::Exempt]);

        $item = Item::query()->findOrFail($id);
        expect($item->name)->toBe('Chapati (2)')
            ->and($item->type)->toBe(ItemType::Product)
            ->and($item->price_cents)->toBe(5_000)
            ->and($item->vat_category)->toBe(VatCategory::Exempt)
            ->and($item->category_id)->toBeNull()
            ->and($item->sku)->toBeNull()
            ->and($item->barcode)->toBeNull()
            ->and($item->is_active)->toBeTrue();
    });
});

it('refuses prices of zero or above the ceiling, and types the menu does not manage', function (array $overrides) {
    $this->tenant->run(fn () => saveItem($overrides));
})->throws(InvalidArgumentException::class)->with([
    'zero' => [['price' => Money::zero()]],
    'too high' => [['price' => Money::fromCents(SaveMenuItem::MAX_PRICE_CENTS + 1)]],
    'modifier' => [['type' => ItemType::Modifier]],
    'bundle' => [['type' => ItemType::Bundle]],
    'unknown category' => [['categoryId' => '00000000-0000-0000-0000-000000000000']],
]);

it('keeps SKUs unique on the whole menu and barcodes unique among items on sale', function () {
    $this->tenant->run(function () {
        saveItem(['sku' => 'CH-1']);

        expect(fn () => saveItem(['name' => 'Other', 'sku' => 'CH-1']))->toThrow(MenuConflict::class, MenuConflict::SKU_TAKEN)
            ->and(fn () => saveItem(['name' => 'Other', 'barcode' => '6001']))->toThrow(MenuConflict::class, MenuConflict::BARCODE_TAKEN);

        // Archived, the soda gives its barcode up, and can't take it back while another item uses it.
        app(SetMenuItemOnSale::class)->handle($this->soda->id, false);
        $juice = saveItem(['name' => 'Juice', 'barcode' => '6001']);

        expect(fn () => app(SetMenuItemOnSale::class)->handle($this->soda->id, true))->toThrow(MenuConflict::class, MenuConflict::BARCODE_TAKEN)
            ->and($this->soda->fresh()?->is_active)->toBeFalse();

        app(SetMenuItemOnSale::class)->handle($juice, false);
        app(SetMenuItemOnSale::class)->handle($this->soda->id, true);
        expect($this->soda->fresh()?->is_active)->toBeTrue();
    });
});

it('keeps category names unique, ignoring case and spaces', function () {
    $this->tenant->run(function () {
        expect(fn () => app(SaveCategory::class)->handle(null, ' drinks ', 0))->toThrow(MenuConflict::class, MenuConflict::CATEGORY_NAME_TAKEN);

        app(SaveCategory::class)->handle($this->drinks->id, 'DRINKS', 2);
        expect($this->drinks->fresh()?->name)->toBe('DRINKS')
            ->and($this->drinks->fresh()?->sort_order)->toBe(2);
    });
});

it('leaves a deleted category’s items on the menu and marks them changed for the tills', function () {
    $this->travel(-1)->hours();
    $this->tenant->run(fn () => $this->soda->touch());
    $this->travelBack();

    $this->tenant->run(function () {
        app(DeleteCategory::class)->handle($this->drinks->id);

        $soda = $this->soda->fresh();
        expect(Category::query()->count())->toBe(0)
            ->and($soda?->category_id)->toBeNull()
            ->and($soda?->is_active)->toBeTrue()
            ->and($soda?->updated_at->greaterThan(now()->subMinute()))->toBeTrue();
    });
});

it('pages, filters and searches the menu, with % and _ taken literally', function () {
    $this->tenant->run(function () {
        saveItem(['name' => '50% juice']);
        saveItem(['name' => '500 ml water', 'sku' => 'W_500']);
        $archived = saveItem(['name' => 'Old 50% deal']);
        app(SetMenuItemOnSale::class)->handle($archived, false);
        Item::create(['type' => ItemType::Modifier, 'name' => 'Extra 50% cheese', 'price_cents' => 1_000, 'vat_category' => VatCategory::Standard]);

        $list = app(ListMenuItems::class);

        expect(array_column($list->handle('50%')['rows'], 'name'))->toBe(['50% juice'])
            ->and(array_column($list->handle('W_5')['rows'], 'name'))->toBe(['500 ml water'])
            ->and(array_column($list->handle('6001')['rows'], 'name'))->toBe(['Soda'])
            ->and(array_column($list->handle(status: ListMenuItems::ARCHIVED)['rows'], 'name'))->toBe(['Old 50% deal'])
            ->and(array_column($list->handle(categoryId: $this->drinks->id)['rows'], 'name'))->toBe(['Soda'])
            ->and(array_column($list->handle(categoryId: ListMenuItems::NO_CATEGORY)['rows'], 'name'))->toEqualCanonicalizing(['50% juice', '500 ml water'])
            ->and($list->handle(page: 2, perPage: 2))->toMatchArray(['total' => 3])
            ->and(array_column($list->handle(page: 2, perPage: 2)['rows'], 'name'))->toBe(['Soda']);
    });
});

it('lets the owner add, edit, archive and restore items from the panel', function () {
    signInToMenu($this->tenant, $this->owner);

    Livewire::test(MenuItems::class)
        ->assertSee('Soda')
        ->assertSee('KES 80.00')
        ->callAction(TestAction::make('addItem')->table(), [
            'name' => 'Mandazi', 'type' => 'product', 'price' => '1,250.5', 'vat_category' => 'standard', 'category_id' => $this->drinks->id,
        ])
        ->assertHasNoFormErrors()
        ->callAction(TestAction::make('editItem')->table($this->soda->id), ['price' => '90', 'name' => 'Soda 500ml'])
        ->assertHasNoFormErrors()
        ->callAction(TestAction::make('archiveItem')->table($this->soda->id));

    $mandazi = Item::query()->where('name', 'Mandazi')->firstOrFail();
    expect($mandazi->price_cents)->toBe(125_050)
        ->and($mandazi->category_id)->toBe($this->drinks->id)
        ->and($this->soda->fresh()?->price_cents)->toBe(9_000)
        ->and($this->soda->fresh()?->name)->toBe('Soda 500ml')
        ->and($this->soda->fresh()?->is_active)->toBeFalse();

    Livewire::test(MenuItems::class)
        ->assertDontSee('Soda 500ml')
        ->filterTable('status', ListMenuItems::ARCHIVED)
        ->assertSee('Soda 500ml')
        ->callAction(TestAction::make('restoreItem')->table($this->soda->id));

    expect($this->soda->fresh()?->is_active)->toBeTrue();
});

it('refuses prices it would have to round, and shows a clash instead of saving', function (string $price) {
    signInToMenu($this->tenant, $this->owner);

    Livewire::test(MenuItems::class)
        ->callAction(TestAction::make('addItem')->table(), ['name' => 'Mandazi', 'type' => 'product', 'price' => $price, 'vat_category' => 'standard'])
        ->assertHasFormErrors(['price']);

    expect(Item::query()->where('name', 'Mandazi')->exists())->toBeFalse();
})->with(['0', '12.345', '-5', 'abc', '10000000.01']);

it('keeps the form open on a barcode clash', function () {
    signInToMenu($this->tenant, $this->owner);

    Livewire::test(MenuItems::class)
        ->callAction(TestAction::make('addItem')->table(), ['name' => 'Fake soda', 'type' => 'product', 'price' => '80', 'vat_category' => 'standard', 'barcode' => '6001'])
        ->assertNotified(__('owner.menu.conflict.barcode_taken'));

    expect(Item::query()->where('name', 'Fake soda')->exists())->toBeFalse();
});

it('lets the owner manage categories', function () {
    signInToMenu($this->tenant, $this->owner);

    Livewire::test(MenuCategories::class)
        ->assertSee('Drinks')
        ->callAction(TestAction::make('addCategory')->table(), ['name' => 'Food', 'sort_order' => 0])
        ->callAction(TestAction::make('editCategory')->table($this->drinks->id), ['name' => 'Cold drinks', 'sort_order' => 3])
        ->callAction(TestAction::make('addCategory')->table(), ['name' => 'food', 'sort_order' => 0])
        ->assertNotified(__('owner.menu.conflict.category_name_taken'));

    expect(Category::query()->orderBy('name')->pluck('name')->all())->toBe(['Cold drinks', 'Food']);

    Livewire::test(MenuCategories::class)->callAction(TestAction::make('deleteCategory')->table($this->drinks->id));

    expect(Category::query()->pluck('name')->all())->toBe(['Food'])
        ->and($this->soda->fresh()?->category_id)->toBeNull();
});

it('shows managers the menu but lets them change nothing', function () {
    signInToMenu($this->tenant, $this->manager);

    Livewire::test(MenuItems::class)
        ->assertSee('Soda')
        ->assertSee(__('owner.menu.read_only'))
        ->assertActionHidden(TestAction::make('addItem')->table())
        ->assertActionHidden(TestAction::make('editItem')->table($this->soda->id))
        ->assertActionHidden(TestAction::make('archiveItem')->table($this->soda->id));

    Livewire::test(MenuCategories::class)
        ->assertSee('Drinks')
        ->assertActionHidden(TestAction::make('addCategory')->table())
        ->assertActionHidden(TestAction::make('deleteCategory')->table($this->drinks->id));

    // Even forced past the hidden buttons, the server refuses.
    foreach ([
        [MenuItems::class, TestAction::make('archiveItem')->table($this->soda->id), []],
        [MenuItems::class, TestAction::make('addItem')->table(), ['name' => 'Sneaky', 'type' => 'product', 'price' => '1', 'vat_category' => 'standard']],
        [MenuCategories::class, TestAction::make('deleteCategory')->table($this->drinks->id), []],
    ] as [$page, $action, $data]) {
        try {
            Livewire::test($page)->callAction($action, $data);
        } catch (Throwable) {
            // Filament may refuse with an exception; either way nothing may change.
        }
    }

    expect($this->soda->fresh()?->is_active)->toBeTrue()
        ->and(Item::query()->where('name', 'Sneaky')->exists())->toBeFalse()
        ->and(Category::query()->whereKey($this->drinks->id)->exists())->toBeTrue();
});

it('never shows or changes another tenant’s menu', function () {
    [$other] = tenantWithBranch();
    $theirs = $other->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Their secret dish', 'price_cents' => 1_000, 'vat_category' => VatCategory::Standard]));

    signInToMenu($this->tenant, $this->owner);

    Livewire::test(MenuItems::class)
        ->assertSee('Soda')
        ->assertDontSee('Their secret dish')
        ->searchTable('secret')
        ->assertDontSee('Their secret dish');

    try {
        Livewire::test(MenuItems::class)->callAction(TestAction::make('archiveItem')->table($theirs->id));
    } catch (Throwable) {
        // Not a record of this table; refused either way.
    }

    tenancy()->end();
    expect($other->run(fn () => $theirs->fresh()?->is_active))->toBeTrue();
});

it('says products, not menu, to a business without the restaurant module', function (array $modules, string $group, string $items, string $whatChanges) {
    $this->tenant->update(['modules' => $modules]);
    signInToMenu($this->tenant, $this->manager);

    expect(MenuItems::getNavigationGroup())->toBe($group)
        ->and(MenuCategories::getNavigationGroup())->toBe($group)
        ->and(MenuItems::getNavigationLabel())->toBe($items);

    Livewire::test(MenuItems::class)->assertSee("Only the owner can change {$whatChanges}.");
})->with([
    'restaurant' => [['restaurant'], 'Menu', 'Items', 'the menu'],
    'restaurant and shop' => [['restaurant', 'shop'], 'Menu', 'Items', 'the menu'],
    'shop only' => [['shop', 'general'], 'Products', 'Products', 'products'],
]);
