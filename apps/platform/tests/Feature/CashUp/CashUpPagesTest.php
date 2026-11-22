<?php

declare(strict_types=1);

use App\Domain\CashUp\Models\CashCheck;
use App\Domain\CashUp\Models\CashMovement;
use App\Domain\CashUp\Models\Shift;
use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Notifications\CashChecksNotice;
use App\Filament\Owner\Pages\CashChecks;
use App\Filament\Owner\Pages\CashUps;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\CashMovementKind;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-11-21 12:00:00'); // 15:00 in Nairobi

    [$this->tenant, $this->branch] = tenantWithBranch(BusinessType::Shop);
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->otherBranch = $this->tenant->run(fn () => Branch::create(['name' => 'Kilimani', 'business_type' => BusinessType::Shop]));

    [$this->owner, $this->manager] = $this->tenant->run(function () {
        $owner = Staff::create(['name' => 'Wanjiru Owner']);
        $owner->assignRole('owner');
        $manager = Staff::create(['name' => 'Baraka Manager']);
        $manager->assignRole('manager');
        $manager->branches()->attach($this->branch->id);

        return [$owner, $manager];
    });
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** A shift of the test till at $branchId, closed KES 300 short unless $attributes say otherwise. */
function cashUpShiftAt(string $branchId, array $attributes = []): Shift
{
    return test()->tenant->run(fn () => Shift::create(array_merge([
        'id' => (string) Str::uuid(),
        'branch_id' => $branchId,
        'device_id' => test()->enrolment->device->id,
        'number' => random_int(1, 99_999),
        'opened_at' => now()->subHours(8),
        'opened_by' => test()->manager->id,
        'opening_counts' => ['1000' => 5],
        'float_cents' => 500_000,
        'closed_at' => now(),
        'closed_by' => test()->manager->id,
        'closing_counts' => ['1000' => 4, '500' => 1, '100' => 2],
        'counted_cents' => 470_000,
        'till_expected_cents' => 500_000,
        'cash_taken_cents' => 0,
        'pay_outs_cents' => 0,
        'drops_cents' => 0,
        'void_refunds_cents' => 0,
        'expected_cents' => 500_000,
        'variance_cents' => -30_000,
        'reconciled_at' => now(),
    ], $attributes)));
}

function signInForCashUp(Staff $staff): void
{
    tenancy()->initialize(test()->tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    test()->actingAs($staff, 'tenant');
}

describe('cash-ups page', function () {
    it('shows owners every branch’s shifts, and managers only their own', function () {
        $ours = cashUpShiftAt($this->branch->id, ['number' => 7]);
        $theirs = cashUpShiftAt($this->otherBranch->id, ['number' => 8]);
        $till = $this->enrolment->device->tillCode();

        signInForCashUp($this->owner);
        Livewire::test(CashUps::class)->assertCanSeeTableRecords([$ours, $theirs])->assertSee("{$till}-Z0007")->assertSee('-KES 300.00');

        signInForCashUp($this->manager);
        Livewire::test(CashUps::class)->assertCanSeeTableRecords([$ours])->assertCanNotSeeTableRecords([$theirs]);
    });

    it('opens a shift’s Z detail with the server’s working, the counts and the cash that left the drawer', function () {
        $shift = cashUpShiftAt($this->branch->id, ['number' => 3, 'pay_outs_cents' => 5_000, 'expected_cents' => 495_000, 'till_expected_cents' => 495_000, 'variance_cents' => -25_000]);
        $this->tenant->run(fn () => CashMovement::create([
            'id' => (string) Str::uuid(), 'shift_id' => $shift->id, 'kind' => CashMovementKind::PayOut, 'amount_cents' => 5_000,
            'moved_at' => now()->subHour(), 'staff_id' => $this->manager->id, 'approved_by' => $this->owner->id, 'reason' => 'Charcoal',
        ]));

        signInForCashUp($this->manager);
        Livewire::test(CashUps::class)
            ->mountAction(TestAction::make('view')->table($shift))
            ->assertMountedActionModalSee(['KES 4,950.00', '-KES 250.00', '4 × KES 1,000.00', 'Pay-out', 'Charcoal', 'Wanjiru Owner'])
            ->assertMountedActionModalDontSee('The till expected');
    });

    it('lets only the owner change the allowance, logs who did it, and tells the tills', function () {
        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('cash_up.allowance_changed', Mockery::on(fn (array $context) => $context['from_cents'] === 10_000 && $context['to_cents'] === 25_000 && $context['staff_id'] === $this->owner->id));

        signInForCashUp($this->manager);
        Livewire::test(CashUps::class)->assertActionHidden(TestAction::make('allowance')->table());

        signInForCashUp($this->owner);
        Livewire::test(CashUps::class)
            ->callAction(TestAction::make('allowance')->table(), ['allowance' => '1000000'])
            ->assertHasActionErrors(['allowance']);
        expect($this->tenant->fresh()?->cash_variance_allowed_cents)->toBe(10_000);

        Livewire::test(CashUps::class)
            ->callAction(TestAction::make('allowance')->table(), ['allowance' => '250'])
            ->assertHasNoActionErrors();

        expect($this->tenant->fresh()?->cash_variance_allowed_cents)->toBe(25_000);
    });
});

describe('cash checks', function () {
    it('lists open checks for the person’s branches, and a person marks one checked with a note', function () {
        $ours = cashUpShiftAt($this->branch->id);
        $theirs = cashUpShiftAt($this->otherBranch->id);
        $this->tenant->run(function () use ($ours, $theirs) {
            foreach ([$ours, $theirs] as $shift) {
                CashCheck::open(CashCheck::VARIANCE_OVER_LIMIT, "shift:{$shift->id}", [
                    'branch_id' => $shift->branch_id, 'shift_id' => $shift->id, 'staff_id' => $shift->closed_by, 'amount_cents' => -30_000,
                    'details' => ['number' => $shift->number],
                ]);
            }
        });
        [$ourCheck, $theirCheck] = $this->tenant->run(fn () => [
            CashCheck::query()->where('shift_id', $ours->id)->sole(),
            CashCheck::query()->where('shift_id', $theirs->id)->sole(),
        ]);

        signInForCashUp($this->manager);
        expect(CashChecks::getNavigationBadge())->toBe('1');
        Livewire::test(CashChecks::class)
            ->assertCanSeeTableRecords([$ourCheck])
            ->assertCanNotSeeTableRecords([$theirCheck])
            ->assertSee('KES 300.00 short')
            ->callAction(TestAction::make('resolve')->table($ourCheck), ['note' => 'Recounted with the cashier'])
            ->assertHasNoActionErrors();

        expect($this->tenant->run(fn () => CashCheck::query()->find($ourCheck->id)?->only(['status', 'resolved_by', 'resolved_note'])))
            ->toBe(['status' => CashCheck::RESOLVED, 'resolved_by' => $this->manager->id, 'resolved_note' => 'Recounted with the cashier']);
    });
});

describe('cash-up:check', function () {
    it('flags a shift left open over a day, and a sale naming a shift that never arrived', function () {
        $stale = cashUpShiftAt($this->branch->id, ['opened_at' => now()->subHours(30), 'closed_at' => null, 'closed_by' => null, 'counted_cents' => null, 'expected_cents' => null, 'variance_cents' => null]);
        $fresh = cashUpShiftAt($this->branch->id, ['opened_at' => now()->subHours(2), 'closed_at' => null]);
        $soda = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Soda', 'price_cents' => 8_000, 'vat_category' => VatCategory::Standard]));
        $lost = saleOf([[$soda, 1]], 1, ['shift_id' => (string) Str::uuid(), 'placed_at' => now()->subHours(2)->toIso8601String()]);
        $recent = saleOf([[$soda, 1]], 2, ['shift_id' => (string) Str::uuid(), 'placed_at' => now()->subMinutes(5)->toIso8601String()]);
        $known = saleOf([[$soda, 1]], 3, ['shift_id' => $fresh->id, 'placed_at' => now()->subHours(2)->toIso8601String()]);
        sync([$lost, $recent, $known])->assertJsonPath('results.*.status', ['accepted', 'accepted', 'accepted']);

        $this->artisan('cash-up:check')->assertSuccessful();

        expect($this->tenant->run(fn () => CashCheck::query()->where('status', CashCheck::OPEN)->orderBy('kind')->pluck('dedupe_key')->all()))
            ->toBe(["open_too_long:shift:{$stale->id}", "sale_outside_shift:order:{$lost['uuid']}"]);
    });

    it('tells the owner once per new batch, at most every half hour', function () {
        $shift = cashUpShiftAt($this->branch->id);
        $open = fn (string $kind) => $this->tenant->run(fn () => CashCheck::open($kind, "shift:{$shift->id}", ['branch_id' => $shift->branch_id, 'shift_id' => $shift->id]));

        $open(CashCheck::VARIANCE_OVER_LIMIT);
        $this->artisan('cash-up:check');
        Notification::assertSentToTimes($this->tenant, CashChecksNotice::class, 1);
        Notification::assertSentTo($this->tenant, CashChecksNotice::class, fn (CashChecksNotice $notice) => $notice->counts === [CashCheck::VARIANCE_OVER_LIMIT => 1]);

        $open(CashCheck::FIGURES_DIFFER);
        $this->artisan('cash-up:check');
        Notification::assertSentToTimes($this->tenant, CashChecksNotice::class, 1);

        $this->travel(31)->minutes();
        $this->artisan('cash-up:check');
        Notification::assertSentToTimes($this->tenant, CashChecksNotice::class, 2);
    });
});

it('never shows another business’s shifts or cash checks', function () {
    $ours = cashUpShiftAt($this->branch->id, ['number' => 1]);

    [$other, $otherBranch] = tenantWithBranch(BusinessType::Shop);
    $theirTill = enrolFor($other, $otherBranch);
    $theirs = $other->run(function () use ($otherBranch, $theirTill) {
        $shift = Shift::create([
            'id' => (string) Str::uuid(), 'branch_id' => $otherBranch->id, 'device_id' => $theirTill->device->id, 'number' => 99,
            'opened_at' => now()->subHour(), 'opened_by' => (string) Str::uuid(), 'opening_counts' => ['1000' => 1], 'float_cents' => 100_000,
        ]);
        CashCheck::open(CashCheck::OPEN_TOO_LONG, "shift:{$shift->id}", ['branch_id' => $otherBranch->id, 'shift_id' => $shift->id]);

        return $shift;
    });

    signInForCashUp($this->owner);
    Livewire::test(CashUps::class)->assertCanSeeTableRecords([$ours])->assertCanNotSeeTableRecords([$theirs])->assertDontSee('Z0099');
    Livewire::test(CashChecks::class)->assertCountTableRecords(0);
    expect(CashChecks::getNavigationBadge())->toBeNull();
});
