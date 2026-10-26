<?php

declare(strict_types=1);

use App\Domain\Payments\Actions\SavePaymentAccount;
use App\Domain\Payments\Events\MpesaConfirmed;
use App\Domain\Payments\Gateways\PaymentGateways;
use App\Domain\Payments\Jobs\RegisterConfirmationUrls;
use App\Domain\Payments\Models\C2bRoute;
use App\Domain\Payments\Models\MpesaConfirmation;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Webhooks\WebhookReceipt;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\MobileMoneyProvider;

uses(DatabaseMigrations::class);

const C2B_SAFARICOM_IP = '196.201.214.200';

beforeEach(function () {
    config(['vendapos.payments.fake' => false]);
    Cache::flush();
    Http::preventStrayRequests();
    Notification::fake();

    // tenantWithBranch() gives the business a default sandbox account on shortcode 174379.
    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->account = $this->tenant->run(fn () => PaymentAccount::query()->sole());
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function fakeRegisterUrl(array $answer = ['OriginatorCoversationID' => '7619-37765134-1', 'ResponseCode' => '0', 'ResponseDescription' => 'success'], int $status = 200): void
{
    Http::fake([
        'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'sandbox-access-token', 'expires_in' => '3599']),
        'sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl' => Http::response($answer, $status),
    ]);
}

/** Registers $account's URLs and answers its fresh token. */
function registeredToken(Tenant $tenant, PaymentAccount $account): string
{
    fakeRegisterUrl();
    (new RegisterConfirmationUrls($tenant->id, $account->id))->handle(app(PaymentGateways::class));

    return (string) $tenant->run(fn () => PaymentAccount::query()->findOrFail($account->id)->c2b_token);
}

/** @return array<string, string> Daraja's C2B confirmation body. */
function c2bBody(array $overrides = []): array
{
    return array_merge([
        'TransactionType' => 'Pay Bill',
        'TransID' => 'TJL7QW2E9R',
        'TransTime' => '20261026143005',
        'TransAmount' => '1850.00',
        'BusinessShortCode' => '174379',
        'BillRefNumber' => 'TABLE 4',
        'InvoiceNumber' => '',
        'OrgAccountBalance' => '',
        'ThirdPartyTransID' => '',
        'MSISDN' => '25471****678',
        'FirstName' => 'Amina',
        'MiddleName' => '',
        'LastName' => 'Otieno',
    ], $overrides);
}

function postConfirmation(string $token, array $body, string $ip = C2B_SAFARICOM_IP): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson("/webhooks/c2b/{$token}/confirm", $body);
}

it('registers the confirmation URL with Safaricom when new keys are saved', function () {
    Queue::fake();
    Http::fake(['sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'token', 'expires_in' => '3599'])]);

    $this->tenant->run(fn () => app(SavePaymentAccount::class)->handle(null, 'Mtwapa till', MobileMoneyProvider::MpesaKe, 'sandbox', [
        'consumer_key' => 'k', 'consumer_secret' => 's', 'passkey' => 'p', 'shortcode' => '600111', 'party_b' => '600111', 'transaction_type' => 'CustomerPayBillOnline',
    ], false, null));

    Queue::assertPushed(RegisterConfirmationUrls::class, fn (RegisterConfirmationUrls $job) => $job->tenantId === $this->tenant->id);
});

it('gives the account a secret URL, routes it centrally and tells Safaricom', function () {
    $token = registeredToken($this->tenant, $this->account);
    $account = $this->tenant->run(fn () => $this->account->fresh());

    expect($token)->toHaveLength(48)
        ->and($account?->c2b_status)->toBe(PaymentAccount::C2B_REGISTERED)
        ->and(C2bRoute::forToken($token))->tenant_id->toBe($this->tenant->id)->payment_account_id->toBe($this->account->id)
        ->and(DB::table('c2b_routes')->value('token_hash'))->not->toBe($token);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/mpesa/c2b/v1/registerurl')
        && $request['ShortCode'] === '174379'
        && $request['ResponseType'] === 'Completed'
        && str_ends_with($request['ConfirmationURL'], "/webhooks/c2b/{$token}/confirm")
        && str_ends_with($request['ValidationURL'], "/webhooks/c2b/{$token}/validate"));
});

it('keeps the confirmation URL free of the words Safaricom refuses', function () {
    registeredToken($this->tenant, $this->account);

    expect($this->tenant->run(fn () => $this->account->fresh()?->confirmationUrl()))
        ->not->toMatch('/m-?pesa|safaricom|exe|cmd|sql|query/i');
});

it('shows the owner why Safaricom refused the registration', function () {
    fakeRegisterUrl(['requestId' => '1', 'errorCode' => '500.003.1001', 'errorMessage' => 'Urls are already registered'], 500);

    (new RegisterConfirmationUrls($this->tenant->id, $this->account->id))->handle(app(PaymentGateways::class));

    expect($this->tenant->run(fn () => $this->account->fresh()))
        ->c2b_status->toBe(PaymentAccount::C2B_FAILED)
        ->c2b_message->toBe('Urls are already registered');
});

it('stores a confirmation once, in shillings to cents, Nairobi time to UTC, phone still masked', function () {
    Event::fake([MpesaConfirmed::class]);
    $token = registeredToken($this->tenant, $this->account);

    postConfirmation($token, c2bBody())->assertOk()->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    postConfirmation($token, c2bBody());

    $confirmation = $this->tenant->run(fn () => MpesaConfirmation::query()->sole());
    $paidAt = $this->tenant->run(fn () => MpesaConfirmation::query()->sole()->paid_at->toIso8601String());

    expect($confirmation)
        ->trans_id->toBe('TJL7QW2E9R')
        ->amount_cents->toBe(185_000)
        ->msisdn->toBe('25471****678')
        ->payer_name->toBe('Amina Otieno')
        ->payment_account_id->toBe($this->account->id)
        ->and($paidAt)->toBe('2026-10-26T11:30:05+00:00')
        ->and(WebhookReceipt::query()->pluck('note')->all())->toBe([null, 'duplicate']);

    Event::assertDispatchedTimes(MpesaConfirmed::class, 1);
});

it('gives every refused caller the same reply and stores nothing', function (Closure $call, string $note) {
    $token = registeredToken($this->tenant, $this->account);

    $call($token)->assertOk()->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Success']);

    expect($this->tenant->run(fn () => MpesaConfirmation::query()->count()))->toBe(0)
        ->and(WebhookReceipt::query()->sole()->note)->toBe($note);
})->with([
    'unknown token' => [fn () => postConfirmation(str_repeat('b', 48), c2bBody()), 'unknown_token'],
    'outside Safaricom' => [fn (string $token) => postConfirmation($token, c2bBody(), '203.0.113.9'), 'ip_not_allowed'],
    'another shortcode' => [fn (string $token) => postConfirmation($token, c2bBody(['BusinessShortCode' => '999999'])), 'shortcode_mismatch'],
    'not a confirmation' => [fn (string $token) => postConfirmation($token, ['hello' => 'world']), 'unparseable'],
    'amount with a float exponent' => [fn (string $token) => postConfirmation($token, c2bBody(['TransAmount' => '1e3'])), 'unparseable'],
]);

it('keeps one business’s confirmations away from another', function () {
    [$other] = tenantWithBranch();
    $otherAccount = $other->run(fn () => PaymentAccount::query()->sole());
    $ours = registeredToken($this->tenant, $this->account);
    registeredToken($other, $otherAccount);

    postConfirmation($ours, c2bBody());

    expect($this->tenant->run(fn () => MpesaConfirmation::query()->count()))->toBe(1)
        ->and($other->run(fn () => MpesaConfirmation::query()->count()))->toBe(0);
});

it('always accepts on the validation URL', function () {
    $token = registeredToken($this->tenant, $this->account);

    $this->postJson("/webhooks/c2b/{$token}/validate", c2bBody())->assertOk()->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
});
