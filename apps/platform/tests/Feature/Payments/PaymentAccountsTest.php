<?php

declare(strict_types=1);

use App\Domain\Payments\Actions\AssignPaymentAccountBranches;
use App\Domain\Payments\Actions\SavePaymentAccount;
use App\Domain\Payments\Exceptions\PaymentAccountRefused;
use App\Domain\Payments\Gateways\DarajaGateway;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Payments\Models\PaymentAccountChange;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Notifications\PaymentAccountChangedNotice;
use App\Filament\Owner\Pages\PaymentAccounts;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\MobileMoneyProvider;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    Notification::fake();
    // Saving new keys also registers C2B URLs (tested in C2bTest); Safaricom accepts here.
    Http::fake(['sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl' => Http::response(['ResponseCode' => '0', 'ResponseDescription' => 'success'])]);

    // tenantWithBranch() gives the business a default sandbox account.
    [$this->tenant, $this->nyali] = tenantWithBranch();
    $this->westlands = $this->tenant->run(fn () => Branch::create(['name' => 'Westlands', 'business_type' => BusinessType::Restaurant]));
    $this->default = $this->tenant->run(fn () => PaymentAccount::query()->sole());
    $this->enrolment = enrolFor($this->tenant, $this->nyali);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** Daraja's OAuth endpoint answering with $status for every key. */
function fakeDarajaOauth(int $status = 200): void
{
    Http::fake(['sandbox.safaricom.co.ke/oauth/v1/generate*' => $status === 200
        ? Http::response(['access_token' => 'sandbox-access-token', 'expires_in' => '3599'])
        : Http::response(['errorMessage' => 'Invalid credentials'], $status)]);
}

/** @return array<string, string> */
function accountKeys(string $prefix): array
{
    return [
        'consumer_key' => "{$prefix}-consumer-key",
        'consumer_secret' => "{$prefix}-consumer-secret",
        'passkey' => "{$prefix}-passkey-7d1e",
        'shortcode' => '600111',
        'party_b' => '600111',
        'transaction_type' => 'CustomerPayBillOnline',
    ];
}

function saveAccount(?int $id, string $name, array $keys, bool $isDefault = false, string $environment = PaymentAccount::SANDBOX, ?string $by = null): PaymentAccount
{
    return test()->tenant->run(fn () => app(SavePaymentAccount::class)
        ->handle($id, $name, MobileMoneyProvider::MpesaKe, $environment, $keys, $isDefault, $by));
}

function signInToPaymentAccounts(Tenant $tenant, Staff $staff): void
{
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    test()->actingAs($staff, 'tenant');
}

/** A staff member who finished setup, with an authenticator secret; returns them and a code that works now. */
function staffWithAuthenticator(Tenant $tenant, string $role): array
{
    $provider = Filament::getPanel('owner')->getMultiFactorAuthenticationProviders()['app'];
    assert($provider instanceof AppAuthentication);
    $secret = $provider->generateSecret();

    $staff = $tenant->run(function () use ($role, $secret) {
        $staff = Staff::create(['name' => ucfirst($role).' Wanjiru', 'username' => $role, 'password' => 'correct-horse-9']);
        $staff->forceFill(['app_authentication_secret' => $secret])->save();
        $staff->assignRole($role);

        return $staff;
    });

    return [$staff, $provider->getCurrentCode($staff, $secret)];
}

it('takes a payment into the branch’s own account, and the default everywhere else', function () {
    $nyaliPaybill = paymentAccountFor($this->tenant, ['name' => 'Nyali paybill']);
    $this->tenant->run(fn () => app(AssignPaymentAccountBranches::class)->handle($nyaliPaybill->id, [$this->nyali->id], null));

    $nyali = paymentOf();
    requestPayment($nyali)->assertAccepted();

    $this->enrolment = enrolFor($this->tenant, $this->westlands);
    $westlands = paymentOf();
    requestPayment($westlands)->assertAccepted();

    expect(paymentIn($this->tenant, $nyali['uuid'])->payment_account_id)->toBe($nyaliPaybill->id)
        ->and(paymentIn($this->tenant, $westlands['uuid'])->payment_account_id)->toBe($this->default->id);
});

it('refuses a payment when the branch has no account and there is no default', function () {
    $this->tenant->run(fn () => PaymentAccount::query()->delete());

    requestPayment(paymentOf())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'payment_account_missing');

    expect($this->tenant->run(fn () => Payment::query()->count()))->toBe(0);
});

it('keeps pushing and querying a payment with the keys it was made with after the branch moves', function () {
    config(['vendapos.payments.fake' => false]);
    $old = paymentAccountFor($this->tenant, ['name' => 'Old paybill', 'credentials' => accountKeys('old')]);
    $new = paymentAccountFor($this->tenant, ['name' => 'New paybill', 'credentials' => accountKeys('new')]);
    $this->tenant->run(fn () => app(AssignPaymentAccountBranches::class)->handle($old->id, [$this->nyali->id], null));

    Http::fake([
        'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'token', 'expires_in' => '3599']),
        'sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response(['CheckoutRequestID' => 'ws_CO_1', 'ResponseCode' => '0']),
        'sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query' => Http::response(['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'ok']),
    ]);

    $request = paymentOf();
    requestPayment($request)->assertAccepted();
    $this->tenant->run(fn () => app(AssignPaymentAccountBranches::class)->handle($new->id, [$this->nyali->id], null));
    $this->tenant->run(fn () => app(DarajaGateway::class)->query(paymentIn($this->tenant, $request['uuid'])));

    $oldAuth = 'Basic '.base64_encode('old-consumer-key:old-consumer-secret');
    Http::assertSent(fn (Request $sent) => str_contains($sent->url(), '/oauth/') && $sent->hasHeader('Authorization', $oldAuth));
    Http::assertNotSent(fn (Request $sent) => str_contains($sent->url(), '/oauth/') && ! $sent->hasHeader('Authorization', $oldAuth));
    Http::assertSent(fn (Request $sent) => str_ends_with($sent->url(), '/stkpushquery/v1/query') && $sent['BusinessShortCode'] === '600111');
});

it('tries new keys with Safaricom and saves nothing when they are refused or Safaricom is down', function (int $status, string $reason) {
    fakeDarajaOauth($status);

    expect(fn () => saveAccount(null, 'Mtwapa till', accountKeys('mtwapa')))
        ->toThrow(fn (PaymentAccountRefused $e) => expect($e->reason)->toBe($reason));

    expect($this->tenant->run(fn () => PaymentAccount::query()->count()))->toBe(1)
        ->and($this->tenant->run(fn () => PaymentAccountChange::query()->count()))->toBe(0);
    Notification::assertNothingSent();
})->with([
    'keys refused' => [400, 'keys_refused'],
    'Safaricom down' => [503, 'unreachable'],
]);

it('keeps one environment per business', function () {
    fakeDarajaOauth();

    expect(fn () => saveAccount(null, 'Live paybill', accountKeys('live'), environment: PaymentAccount::PRODUCTION))
        ->toThrow(fn (PaymentAccountRefused $e) => expect($e->reason)->toBe('environment_mixed'));
});

it('keeps saved keys left blank, and audits field names but never key values', function () {
    fakeDarajaOauth();
    [$owner] = staffWithAuthenticator($this->tenant, 'owner');

    saveAccount($this->default->id, 'Main paybill', [
        'consumer_key' => '', 'consumer_secret' => null, 'passkey' => 'rotated-passkey-55aa',
        'shortcode' => '174379', 'party_b' => '174379', 'transaction_type' => 'CustomerPayBillOnline',
    ], isDefault: true, by: $owner->id);

    $account = $this->tenant->run(fn () => $this->default->fresh());
    $change = $this->tenant->run(fn () => PaymentAccountChange::query()->sole());

    expect($account->name)->toBe('Main paybill')
        ->and($account->credentials['consumer_key'])->toBe('sandbox-consumer-key')
        ->and($account->credentials['passkey'])->toBe('rotated-passkey-55aa')
        ->and($change->action)->toBe(PaymentAccountChange::ACTION_UPDATED)
        ->and($change->changed_by)->toBe($owner->id)
        ->and($change->details)->toMatchArray(['fields' => ['passkey'], 'name' => ['from' => 'Default', 'to' => 'Main paybill']])
        ->and(json_encode($change->details))->not->toContain('rotated-passkey');

    Notification::assertSentTo($this->tenant, PaymentAccountChangedNotice::class, fn (PaymentAccountChangedNotice $notice) => $notice->accountName === 'Main paybill' && $notice->changedBy === 'Owner Wanjiru');
});

it('renames without calling Safaricom', function () {
    saveAccount($this->default->id, 'Renamed', [], isDefault: true);

    Http::assertNothingSent();
    expect($this->tenant->run(fn () => $this->default->fresh()?->name))->toBe('Renamed');
});

it('hands the default over to a new account, and never leaves the business without one', function () {
    fakeDarajaOauth();

    $new = saveAccount(null, 'New main', accountKeys('main'), isDefault: true);

    expect($this->tenant->run(fn () => PaymentAccount::defaultFor(MobileMoneyProvider::MpesaKe)?->id))->toBe($new->id)
        ->and(fn () => saveAccount($new->id, 'New main', [], isDefault: false))
        ->toThrow(fn (PaymentAccountRefused $e) => expect($e->reason)->toBe('default_required'));
});

it('moves branches between accounts, sending a branch taken off back to the default', function () {
    $first = paymentAccountFor($this->tenant, ['name' => 'First']);
    $second = paymentAccountFor($this->tenant, ['name' => 'Second']);
    $assign = fn (PaymentAccount $account, array $branches) => $this->tenant->run(fn () => app(AssignPaymentAccountBranches::class)->handle($account->id, $branches, null));
    $accountOf = fn (Branch $branch) => $this->tenant->run(fn () => PaymentAccount::forBranch(MobileMoneyProvider::MpesaKe, $branch->id)?->name);

    $assign($first, [$this->nyali->id, $this->westlands->id]);
    $assign($second, [$this->westlands->id]);

    expect($accountOf($this->nyali))->toBe('First')->and($accountOf($this->westlands))->toBe('Second');

    $assign($first, []);

    expect($accountOf($this->nyali))->toBe('Default')
        ->and($this->tenant->run(fn () => PaymentAccountChange::query()->where('payment_account_id', $first->id)->latest('id')->value('details')))
        ->toBe(['removed' => [$this->nyali->id]]);
});

it('keeps each business’s accounts to itself', function () {
    [$other, $otherBranch] = tenantWithBranch();
    paymentAccountFor($other, ['name' => 'Their secret paybill']);

    // Our branch ID means nothing in their schema, and their accounts never show in ours.
    expect($other->run(fn () => PaymentAccount::forBranch(MobileMoneyProvider::MpesaKe, $this->nyali->id)?->name))->toBe('Default')
        ->and($this->tenant->run(fn () => PaymentAccount::query()->pluck('name')->all()))->toBe(['Default']);

    [$owner] = staffWithAuthenticator($this->tenant, 'owner');
    signInToPaymentAccounts($this->tenant, $owner);

    Livewire::test(PaymentAccounts::class)
        ->assertCanSeeTableRecords([$this->default])
        ->assertDontSee('Their secret paybill');
});

it('lets only owners open the page', function () {
    [$manager] = staffWithAuthenticator($this->tenant, 'manager');
    signInToPaymentAccounts($this->tenant, $manager);

    Livewire::test(PaymentAccounts::class)->assertForbidden();
});

it('shows only the last four characters of a key', function () {
    [$owner] = staffWithAuthenticator($this->tenant, 'owner');
    signInToPaymentAccounts($this->tenant, $owner);

    Livewire::test(PaymentAccounts::class)
        ->assertSee('•••• -key')
        ->assertDontSee('sandbox-consumer-key')
        ->assertDontSee('sandbox-consumer-secret');
});

it('saves an account from the page only with a fresh authenticator code', function () {
    fakeDarajaOauth();
    [$owner, $code] = staffWithAuthenticator($this->tenant, 'owner');
    signInToPaymentAccounts($this->tenant, $owner);
    $form = [...accountKeys('mtwapa'), 'name' => 'Mtwapa till', 'transaction_type' => 'CustomerBuyGoodsOnline'];

    Livewire::test(PaymentAccounts::class)
        ->callAction(TestAction::make('addAccount')->table(), [...$form, 'code' => '000000'])
        ->assertHasActionErrors(['code']);

    expect(PaymentAccount::query()->count())->toBe(1);

    Livewire::test(PaymentAccounts::class)
        ->callAction(TestAction::make('addAccount')->table(), [...$form, 'code' => $code])
        ->assertHasNoActionErrors();

    $saved = PaymentAccount::query()->where('name', 'Mtwapa till')->sole();

    expect($saved->environment)->toBe(PaymentAccount::SANDBOX)
        ->and($saved->is_default)->toBeFalse()
        ->and($saved->credentials['transaction_type'])->toBe('CustomerBuyGoodsOnline')
        ->and(PaymentAccountChange::query()->where('payment_account_id', $saved->id)->value('changed_by'))->toBe($owner->id);
});

it('assigns branches from the page, with a code, to this business’s branches only', function () {
    [$owner, $code] = staffWithAuthenticator($this->tenant, 'owner');
    $paybill = paymentAccountFor($this->tenant, ['name' => 'Nyali paybill']);
    signInToPaymentAccounts($this->tenant, $owner);

    Livewire::test(PaymentAccounts::class)
        ->callAction(TestAction::make('assignBranches')->table($paybill), ['branch_ids' => [$this->nyali->id, 'not-a-branch'], 'code' => '000000'])
        ->assertHasActionErrors(['branch_ids.1', 'code']);

    expect(DB::table('payment_account_branches')->count())->toBe(0);

    Livewire::test(PaymentAccounts::class)
        ->callAction(TestAction::make('assignBranches')->table($paybill), ['branch_ids' => [$this->nyali->id], 'code' => '000000'])
        ->assertHasActionErrors(['code']);

    expect(DB::table('payment_account_branches')->count())->toBe(0);

    Livewire::test(PaymentAccounts::class)
        ->callAction(TestAction::make('assignBranches')->table($paybill), ['branch_ids' => [$this->nyali->id], 'code' => $code])
        ->assertHasNoActionErrors();

    expect(DB::table('payment_account_branches')->pluck('branch_id')->all())->toBe([$this->nyali->id]);
});
