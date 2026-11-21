<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Orders\Models\VoidCheck;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Notifications\VoidChecksNotice;
use App\Domain\Verticals\Actions\ManageFloor;
use App\Filament\Owner\Pages\VoidChecks;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-22 06:00:00'); // 09:00 the day after the test sales, in Nairobi

    [$this->tenant, $this->branch] = tenantWithBranch(BusinessType::Restaurant);
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->chips = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Chips', 'price_cents' => 20_000, 'vat_category' => VatCategory::Standard]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** A staff member of the tenant with $role, at the test branch. */
function memberWith(string $role, string $name): string
{
    return test()->tenant->run(function () use ($role, $name) {
        $staff = Staff::create(['name' => $name]);
        $staff->assignRole($role);
        $staff->branches()->attach(test()->branch->id);

        return $staff->id;
    });
}

/** @return list<string> kinds of the open checks, sorted */
function openVoidChecks(): array
{
    return test()->tenant->run(fn () => VoidCheck::query()->where('status', VoidCheck::OPEN)->orderBy('kind')->pluck('kind')->all());
}

it('records a sale void approved by a cashier, and asks the owner to check it', function () {
    $sale = saleOf([[$this->chips, 1]]);
    sync([$sale])->assertOk();

    sync([voidOf($sale, ['approved_by' => sellerFor($this->tenant)])])->assertJsonPath('results.0.status', 'updated');

    expect(openVoidChecks())->toBe([VoidCheck::APPROVER_NOT_ALLOWED]);
    $check = $this->tenant->run(fn () => VoidCheck::query()->sole());
    expect($check->only(['order_id', 'staff_id', 'approved_by', 'branch_id']))->toBe([
        'order_id' => $sale['uuid'], 'staff_id' => $sale['staff_id'], 'approved_by' => sellerFor($this->tenant), 'branch_id' => $this->branch->id,
    ])->and($check->business_date?->toDateString())->toBe('2026-10-21');
});

it('flags a sale voided on a later business day, and nothing for an ordinary void', function () {
    $ordinary = saleOf([[$this->chips, 1]], 1);
    $late = saleOf([[$this->chips, 1]], 2);
    sync([$ordinary, $late])->assertOk();

    sync([
        voidOf($ordinary),
        voidOf($late, ['voided_at' => '2026-10-22T08:30:00+03:00']),
    ])->assertOk();

    expect(openVoidChecks())->toBe([VoidCheck::LATE_VOID])
        ->and($this->tenant->run(fn () => VoidCheck::query()->sole()->order_id))->toBe($late['uuid']);
});

it('flags an item taken off a ticket with a cashier’s approval', function () {
    $table = $this->tenant->run(function () {
        $floor = app(ManageFloor::class);

        return $floor->addTables($floor->saveArea(null, $this->branch->id, 'Main hall')->id, '1', 1)[0];
    });
    $line = (string) Str::uuid();
    $void = (string) Str::uuid();
    $seller = sellerFor($this->tenant);

    app('auth')->forgetGuards();
    $this->withHeaders(['Authorization' => 'Bearer '.$this->enrolment->plainTextToken, 'X-Device-ID' => $this->enrolment->device->id])
        ->postJson('/api/v1/tickets/sync', ['tickets' => [[
            'uuid' => (string) Str::uuid(), 'staff_id' => $seller, 'opened_at' => '2026-10-22T08:00:00+03:00', 'table_id' => $table->id, 'label' => null,
            'lines' => [['uuid' => $line, 'item_id' => $this->chips->id, 'name' => 'Chips', 'quantity' => 2, 'unit_price_cents' => 20_000, 'vat_category' => 'standard', 'staff_id' => $seller, 'sent_at' => '2026-10-22T08:01:00+03:00']],
            'voids' => [['uuid' => $void, 'line_uuid' => $line, 'quantity' => 1, 'voided_at' => '2026-10-22T08:05:00+03:00', 'voided_by' => $seller, 'approved_by' => $seller, 'reason' => 'wrong_item', 'note' => null]],
        ]]])
        ->assertJsonPath('results.0.status', 'accepted');

    expect(openVoidChecks())->toBe([VoidCheck::APPROVER_NOT_ALLOWED])
        ->and($this->tenant->run(fn () => VoidCheck::query()->sole()->details))->toBe(['item' => 'Chips', 'quantity' => 1, 'reason' => 'wrong_item']);
});

it('flags staff who voided more than 10% of at least 20 sales in a business day, once', function () {
    $wanjiku = sellerFor($this->tenant);
    $baraka = memberWith('cashier', 'Baraka');
    $quiet = memberWith('cashier', 'Quiet');
    $sales = [];
    $sequence = 0;

    // Wanjiku: 20 sales, 3 voided (15%). Baraka: 20 sales, 2 voided (exactly 10%). Quiet: 10 sales, 2 voided (too few to count).
    foreach ([[$wanjiku, 20, 3], [$baraka, 20, 2], [$quiet, 10, 2]] as [$staffId, $count, $voided]) {
        for ($i = 0; $i < $count; $i++) {
            $sale = saleOf([[$this->chips, 1]], ++$sequence, ['staff_id' => $staffId]);
            $sales[] = $i < $voided ? voidOf($sale) : $sale;
        }
    }
    sync($sales)->assertOk();
    // A sale of the day before does not count towards the 21st.
    sync([saleOf([[$this->chips, 1]], ++$sequence, ['placed_at' => '2026-10-20T23:30:00+03:00'])])->assertOk();

    $this->artisan('orders:void-rates')->assertSuccessful(); // yesterday: 2026-10-21
    $this->artisan('orders:void-rates', ['--date' => '2026-10-21'])->assertSuccessful();

    $checks = $this->tenant->run(fn () => VoidCheck::query()->where('kind', VoidCheck::HIGH_VOID_RATE)->get());
    expect($checks)->toHaveCount(1)
        ->and($checks[0]->staff_id)->toBe($wanjiku)
        ->and($checks[0]->business_date?->toDateString())->toBe('2026-10-21')
        ->and($checks[0]->details)->toBe(['sales' => 20, 'voided' => 3, 'percent' => 15]);
});

it('tells the owner in one digest at most every half hour', function () {
    $first = saleOf([[$this->chips, 1]], 1);
    $second = saleOf([[$this->chips, 1]], 2);
    sync([$first, $second])->assertOk();
    sync([voidOf($first, ['approved_by' => sellerFor($this->tenant)])])->assertOk();

    $this->artisan('orders:check-voids');
    Notification::assertSentToTimes($this->tenant, VoidChecksNotice::class, 1);
    Notification::assertSentTo($this->tenant, VoidChecksNotice::class, fn (VoidChecksNotice $notice) => $notice->counts === [VoidCheck::APPROVER_NOT_ALLOWED => 1]);

    // A new one ten minutes later waits for the next digest.
    $this->travel(10)->minutes();
    sync([voidOf($second, ['voided_at' => '2026-10-22T09:00:00+03:00'])])->assertOk();
    $this->artisan('orders:check-voids');
    Notification::assertSentToTimes($this->tenant, VoidChecksNotice::class, 1);

    $this->travel(21)->minutes();
    $this->artisan('orders:check-voids');
    Notification::assertSentToTimes($this->tenant, VoidChecksNotice::class, 2);
    Notification::assertSentTo($this->tenant, VoidChecksNotice::class, fn (VoidChecksNotice $notice) => $notice->counts === [VoidCheck::LATE_VOID => 1]);
});

it('words every kind of void check for the owner, in English and Swahili', function () {
    foreach (['en', 'sw'] as $locale) {
        foreach (VoidCheck::KINDS as $kind) {
            expect(trans("owner.void_checks.kinds.{$kind}", locale: $locale))->not->toStartWith('owner.')
                ->and(trans_choice("notifications.void_checks.kinds.{$kind}", 2, ['count' => 2], $locale))->not->toStartWith('notifications.');
        }
    }
});

it('shows managers their own branches’ void checks and lets them mark one checked with a note', function () {
    $sale = saleOf([[$this->chips, 1]]);
    sync([$sale])->assertOk();
    sync([voidOf($sale, ['approved_by' => sellerFor($this->tenant)])])->assertOk();

    [$manager, $elsewhere] = $this->tenant->run(function () {
        $westlands = Branch::create(['name' => 'Westlands', 'business_type' => BusinessType::Restaurant]);
        $make = function (string $username, Branch $branch): Staff {
            $staff = Staff::create(['name' => ucfirst($username), 'username' => $username, 'password' => 'correct-horse-9']);
            $staff->assignRole('manager');
            $staff->branches()->attach($branch->id);

            return $staff;
        };

        return [$make('nyali', $this->branch), $make('westlands', $westlands)];
    });

    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    $check = VoidCheck::query()->sole();

    $this->actingAs($elsewhere, 'tenant');
    Livewire::test(VoidChecks::class)->assertCanNotSeeTableRecords([$check]);

    $this->actingAs($manager, 'tenant');
    Livewire::test(VoidChecks::class)
        ->assertCanSeeTableRecords([$check])
        ->assertSee('Achieng Seller')
        ->callAction(TestAction::make('resolve')->table($check), ['note' => 'Spoke to Achieng; the manager was on leave'])
        ->assertHasNoActionErrors();

    expect($check->fresh())->status->toBe(VoidCheck::RESOLVED)->resolved_by->toBe($manager->id);
});

it('keeps each business’s void checks to itself', function () {
    $sale = saleOf([[$this->chips, 1]]);
    sync([$sale])->assertOk();
    sync([voidOf($sale, ['approved_by' => sellerFor($this->tenant)])])->assertOk();

    [$other] = tenantWithBranch();
    $owner = $other->run(function () {
        $staff = Staff::create(['name' => 'Other owner', 'username' => 'owner', 'password' => 'correct-horse-9']);
        $staff->assignRole('owner');

        return $staff;
    });

    tenancy()->initialize($other);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    $this->actingAs($owner, 'tenant');

    Livewire::test(VoidChecks::class)->assertSee(__('owner.void_checks.none'))->assertDontSee('Achieng Seller');
    expect(VoidCheck::query()->count())->toBe(0);
});
