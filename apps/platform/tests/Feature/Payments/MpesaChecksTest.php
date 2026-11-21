<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Payments\Actions\MatchMpesaCodes;
use App\Domain\Payments\Jobs\MatchMpesaConfirmation;
use App\Domain\Payments\Models\MpesaCheck;
use App\Domain\Payments\Models\MpesaCodeClaim;
use App\Domain\Payments\Models\MpesaConfirmation;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Notifications\MpesaChecksNotice;
use App\Filament\Owner\Pages\MpesaChecks;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\PaymentStatus;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-26 10:00:00');

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->account = $this->tenant->run(fn () => PaymentAccount::query()->sole());
    $this->ugali = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Ugali beef', 'price_cents' => 45_000, 'vat_category' => VatCategory::Standard]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** A synced sale paid by one typed M-Pesa code. */
function codeSale(string $code, int $sequence = 1, int $quantity = 1): array
{
    $sale = saleOf([[test()->ugali, $quantity]], $sequence);
    $sale['tenders'] = [['method' => 'mpesa_code', 'amount_cents' => $sale['total_cents'], 'mpesa_reference' => $code]];
    sync([$sale])->assertJsonPath('results.0.status', 'accepted');

    return $sale;
}

/** Safaricom confirms money paid straight into the account, and the matching runs. */
function confirmCode(string $code, int $amountCents): void
{
    $id = test()->tenant->run(fn () => MpesaConfirmation::query()->create([
        'payment_account_id' => test()->account->id, 'trans_id' => $code, 'trans_type' => 'Pay Bill', 'amount_cents' => $amountCents,
        'business_short_code' => '174379', 'paid_at' => now(),
    ])->id);

    (new MatchMpesaConfirmation(test()->tenant->id, $id))->handle(app(MatchMpesaCodes::class));
}

function claimOf(string $code): MpesaCodeClaim
{
    return test()->tenant->run(fn () => MpesaCodeClaim::query()->where('mpesa_code', $code)->sole());
}

/** @return list<string> */
function openChecks(): array
{
    return test()->tenant->run(fn () => MpesaCheck::query()->where('status', MpesaCheck::OPEN)->orderBy('id')->pluck('kind')->all());
}

it('confirms a typed code when Safaricom’s confirmation arrives after the sale', function () {
    codeSale('TJL7QW2E9R');
    expect(claimOf('TJL7QW2E9R')->status)->toBe(MpesaCodeClaim::PENDING);

    confirmCode('TJL7QW2E9R', 45_000);

    expect(claimOf('TJL7QW2E9R'))->status->toBe(MpesaCodeClaim::CONFIRMED)->mpesa_confirmation_id->not->toBeNull()
        ->and(openChecks())->toBe([]);
});

it('confirms a typed code whose confirmation arrived before the sale synced', function () {
    confirmCode('TJL7QW2E9R', 45_000);
    codeSale('TJL7QW2E9R');

    expect(claimOf('TJL7QW2E9R')->status)->toBe(MpesaCodeClaim::CONFIRMED);
});

it('flags a code confirmed for a different amount, and never accepts it', function () {
    codeSale('TJL7QW2E9R');
    confirmCode('TJL7QW2E9R', 4_500);

    $check = $this->tenant->run(fn () => MpesaCheck::query()->sole());

    expect(claimOf('TJL7QW2E9R')->status)->toBe(MpesaCodeClaim::AMOUNT_MISMATCH)
        ->and($check)->kind->toBe(MpesaCheck::AMOUNT_MISMATCH)->amount_cents->toBe(45_000)
        ->and($check->details['confirmed_cents'])->toBe(4_500);
});

it('keeps both sales when a code is typed twice, and flags it once', function () {
    $first = codeSale('TJL7QW2E9R', 1);
    $second = codeSale('TJL7QW2E9R', 2);
    confirmCode('TJL7QW2E9R', 45_000);

    $check = $this->tenant->run(fn () => MpesaCheck::query()->sole());

    expect($this->tenant->run(fn () => MpesaCodeClaim::query()->pluck('status')->unique()->all()))->toBe([MpesaCodeClaim::REUSED])
        ->and($check->kind)->toBe(MpesaCheck::CODE_REUSED)
        ->and($check->details['order_ids'])->toBe([$first['uuid'], $second['uuid']]);
});

it('flags a typed code still unconfirmed after 30 minutes, and settles it if Safaricom confirms later', function () {
    codeSale('TJL7QW2E9R');

    $this->travel(29)->minutes();
    $this->artisan('payments:check-mpesa')->assertSuccessful();
    expect(openChecks())->toBe([]);

    $this->travel(2)->minutes();
    $this->artisan('payments:check-mpesa');
    expect(openChecks())->toBe([MpesaCheck::UNCONFIRMED_CODE])
        ->and(claimOf('TJL7QW2E9R')->status)->toBe(MpesaCodeClaim::UNCONFIRMED);

    confirmCode('TJL7QW2E9R', 45_000);

    expect(openChecks())->toBe([])
        ->and(claimOf('TJL7QW2E9R')->status)->toBe(MpesaCodeClaim::CONFIRMED)
        ->and($this->tenant->run(fn () => MpesaCheck::query()->sole()->resolved_note))->toBe('settled_automatically');
});

it('flags a paid push no sale claims, and settles it when the sale syncs late', function () {
    $payment = paymentOf(['amount_cents' => 45_000]);
    requestPayment($payment)->assertAccepted();
    $this->tenant->run(fn () => Payment::query()->whereKey($payment['uuid'])->update(['status' => PaymentStatus::Succeeded, 'completed_at' => now()]));

    $this->travel(31)->minutes();
    $this->artisan('payments:check-mpesa');
    expect(openChecks())->toBe([MpesaCheck::UNCLAIMED_PUSH]);

    $sale = saleOf([[$this->ugali, 1]], 1, ['uuid' => $payment['order_uuid']]);
    $sale['tenders'] = [['method' => 'mpesa_push', 'amount_cents' => 45_000, 'payment_uuid' => $payment['uuid']]];
    sync([$sale])->assertJsonPath('results.0.status', 'accepted');

    expect(openChecks())->toBe([])
        ->and($this->tenant->run(fn () => Payment::query()->findOrFail($payment['uuid'])->claimed_at))->not->toBeNull();
});

it('tells the owner in one digest at most every half hour', function () {
    codeSale('AAAAAAAAA1', 1);
    codeSale('AAAAAAAAA2', 2);
    $this->travel(5)->minutes();
    codeSale('AAAAAAAAA3', 3);

    // 10:31: the first two are overdue.
    $this->travel(26)->minutes();
    $this->artisan('payments:check-mpesa');
    Notification::assertSentToTimes($this->tenant, MpesaChecksNotice::class, 1);
    Notification::assertSentTo($this->tenant, MpesaChecksNotice::class, fn (MpesaChecksNotice $notice) => $notice->counts === [MpesaCheck::UNCONFIRMED_CODE => 2]);

    // 10:36: the third is overdue too, but waits for the next digest.
    $this->travel(5)->minutes();
    $this->artisan('payments:check-mpesa');
    Notification::assertSentToTimes($this->tenant, MpesaChecksNotice::class, 1);

    // 11:02: half an hour since the last digest.
    $this->travel(26)->minutes();
    $this->artisan('payments:check-mpesa');
    Notification::assertSentToTimes($this->tenant, MpesaChecksNotice::class, 2);
});

it('shows managers their own branches’ checks and lets them mark one checked with a note', function () {
    codeSale('TJL7QW2E9R');
    confirmCode('TJL7QW2E9R', 4_500);

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
    $check = MpesaCheck::query()->sole();

    $this->actingAs($elsewhere, 'tenant');
    Livewire::test(MpesaChecks::class)->assertCanNotSeeTableRecords([$check]);

    $this->actingAs($manager, 'tenant');
    Livewire::test(MpesaChecks::class)
        ->assertCanSeeTableRecords([$check])
        ->callAction(TestAction::make('resolve')->table($check), ['note' => 'Customer topped up the difference in cash'])
        ->assertHasNoActionErrors();

    expect($check->fresh())->status->toBe(MpesaCheck::RESOLVED)->resolved_by->toBe($manager->id);
});

it('keeps each business’s M-Pesa checks to itself', function () {
    codeSale('TJL7QW2E9R');
    confirmCode('TJL7QW2E9R', 4_500);

    [$other] = tenantWithBranch();
    $owner = $other->run(function () {
        $staff = Staff::create(['name' => 'Other owner', 'username' => 'owner', 'password' => 'correct-horse-9']);
        $staff->assignRole('owner');

        return $staff;
    });

    tenancy()->initialize($other);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    $this->actingAs($owner, 'tenant');

    Livewire::test(MpesaChecks::class)->assertSee(__('owner.mpesa_checks.none'))->assertDontSee('TJL7QW2E9R');
    expect(Str::of(json_encode(MpesaCheck::query()->get()))->contains('TJL7QW2E9R'))->toBeFalse();
});

it('claims the M-Pesa of a sale rung up and voided before it synced: the money was still taken', function () {
    $payment = paymentOf(['amount_cents' => 45_000]);
    requestPayment($payment)->assertAccepted();
    $this->tenant->run(fn () => Payment::query()->whereKey($payment['uuid'])->update(['status' => PaymentStatus::Succeeded, 'completed_at' => now()]));

    $pushSale = saleOf([[$this->ugali, 1]], 1, ['uuid' => $payment['order_uuid'], 'status' => 'voided']);
    $pushSale['tenders'] = [['method' => 'mpesa_push', 'amount_cents' => 45_000, 'payment_uuid' => $payment['uuid']]];
    $codeSale = saleOf([[$this->ugali, 1]], 2, ['status' => 'voided']);
    $codeSale['tenders'] = [['method' => 'mpesa_code', 'amount_cents' => 45_000, 'mpesa_reference' => 'TJL7QW2E9V']];
    sync([$pushSale, $codeSale])->assertJsonPath('results.0.status', 'accepted')->assertJsonPath('results.1.status', 'accepted');

    expect($this->tenant->run(fn () => Payment::query()->findOrFail($payment['uuid'])->claimed_at))->not->toBeNull()
        ->and(claimOf('TJL7QW2E9V')->order_id)->toBe($codeSale['uuid']);

    $this->travel(31)->minutes();
    $this->artisan('payments:check-mpesa');
    expect(openChecks())->not->toContain(MpesaCheck::UNCLAIMED_PUSH);
});
