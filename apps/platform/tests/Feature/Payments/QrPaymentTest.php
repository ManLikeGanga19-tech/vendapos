<?php

declare(strict_types=1);

use App\Console\Commands\ReconcilePaymentsCommand;
use App\Domain\Payments\Actions\MatchMpesaCodes;
use App\Domain\Payments\Gateways\FakeGateway;
use App\Domain\Payments\Jobs\MatchMpesaConfirmation;
use App\Domain\Payments\Jobs\QueryPaymentStatus;
use App\Domain\Payments\Models\MpesaCheck;
use App\Domain\Payments\Models\MpesaConfirmation;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\PaymentChannel;
use VendaPOS\Core\Enums\PaymentStatus;

uses(DatabaseMigrations::class);

beforeEach(function () {
    config(['vendapos.payments.fake' => true]);
    Notification::fake();

    // tenantWithBranch() gives the business a default paybill account on 174379.
    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->tenant->update(['name' => 'Mama Oliech Restaurant']);
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->account = $this->tenant->run(fn () => PaymentAccount::query()->sole());
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** A QR payment request for the bill: no phone, the customer scans. */
function qrPaymentOf(array $overrides = []): array
{
    $payment = paymentOf(['channel' => 'qr', 'amount_cents' => 78_000, ...$overrides]);
    unset($payment['phone']);

    return $payment;
}

function showPayment(string $uuid): TestResponse
{
    return deviceRequest('GET', "/api/v1/payments/{$uuid}", test()->enrolment->plainTextToken, test()->enrolment->device->id);
}

/** Safaricom confirms money paid into the account, and the matching runs. */
function confirmPaybill(string $billRef, int $amountCents, string $code = 'TJL7QW2E9R'): int
{
    $id = test()->tenant->run(fn () => MpesaConfirmation::query()->create([
        'payment_account_id' => test()->account->id, 'trans_id' => $code, 'trans_type' => 'Pay Bill', 'amount_cents' => $amountCents,
        'business_short_code' => '174379', 'bill_ref' => $billRef, 'paid_at' => now(),
    ])->id);

    (new MatchMpesaConfirmation(test()->tenant->id, $id))->handle(app(MatchMpesaCodes::class));

    return $id;
}

function qrPayment(string $uuid): Payment
{
    return test()->tenant->run(fn () => Payment::query()->findOrFail($uuid));
}

it('makes a QR for the bill, with the paybill and the account number to type instead', function () {
    $request = qrPaymentOf();

    requestPayment($request)->assertAccepted()->assertJsonPath('channel', 'qr');

    $payment = qrPayment($request['uuid']);
    expect($payment->channel)->toBe(PaymentChannel::Qr)
        ->and($payment->phone)->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->reference)->toMatch('/^VP[23456789ABCDEFGHJKMNPQRSTVWXYZ]{8}$/');

    showPayment($request['uuid'])->assertOk()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('qr.png', FakeGateway::QR_PNG)
        ->assertJsonPath('qr.paybill', '174379')
        ->assertJsonPath('qr.account', $payment->reference);
});

it('refuses scan to pay on a buy goods till, which sends no account number back', function () {
    $this->tenant->run(fn () => $this->account->update(['credentials' => [...$this->account->credentials, 'transaction_type' => 'CustomerBuyGoodsOnline']]));

    requestPayment(qrPaymentOf())->assertUnprocessable()->assertJsonPath('code', 'qr_needs_paybill');

    expect($this->tenant->run(fn () => Payment::query()->count()))->toBe(0);
});

it('takes a QR payment with no phone, but never one with a phone', function () {
    requestPayment(paymentOf(['channel' => 'qr']))->assertUnprocessable()->assertJsonValidationErrors('phone');

    $push = paymentOf();
    unset($push['phone']);
    requestPayment($push)->assertUnprocessable()->assertJsonValidationErrors('phone');
});

it('is paid when Safaricom confirms the bill’s account number for its amount', function () {
    $request = qrPaymentOf();
    requestPayment($request)->assertAccepted();
    $reference = qrPayment($request['uuid'])->reference;

    // Customers who type the account number may type it in lower case.
    confirmPaybill(strtolower((string) $reference), 78_000);

    expect(qrPayment($request['uuid']))->status->toBe(PaymentStatus::Succeeded)->provider_reference->toBe('TJL7QW2E9R');
    showPayment($request['uuid'])->assertJsonPath('status', 'succeeded')->assertJsonPath('provider_reference', 'TJL7QW2E9R');
});

it('leaves the bill unpaid and tells the owner when the amount paid differs', function () {
    $request = qrPaymentOf();
    requestPayment($request)->assertAccepted();

    confirmPaybill((string) qrPayment($request['uuid'])->reference, 50_000);

    $check = $this->tenant->run(fn () => MpesaCheck::query()->sole());
    expect(qrPayment($request['uuid'])->status)->toBe(PaymentStatus::Pending)
        ->and($check)->kind->toBe(MpesaCheck::AMOUNT_MISMATCH)->payment_id->toBe($request['uuid'])
        ->and($check->details['confirmed_cents'])->toBe(50_000);
});

it('tells the owner when a bill already paid is paid again, and ignores a resent confirmation', function () {
    $request = qrPaymentOf();
    requestPayment($request)->assertAccepted();
    $reference = (string) qrPayment($request['uuid'])->reference;

    $id = confirmPaybill($reference, 78_000);
    (new MatchMpesaConfirmation($this->tenant->id, $id))->handle(app(MatchMpesaCodes::class)); // the same confirmation again
    expect($this->tenant->run(fn () => MpesaCheck::query()->count()))->toBe(0);

    confirmPaybill($reference, 78_000, 'TKA1BC2D3E');

    expect($this->tenant->run(fn () => MpesaCheck::query()->sole()))
        ->kind->toBe(MpesaCheck::QR_PAID_TWICE)->mpesa_code->toBe('TKA1BC2D3E');
});

it('never pays another business’s bill or one on another account', function () {
    $request = qrPaymentOf();
    requestPayment($request)->assertAccepted();
    $reference = (string) qrPayment($request['uuid'])->reference;

    // Same reference, but confirmed on another account of this business.
    $other = paymentAccountFor($this->tenant);
    $id = $this->tenant->run(fn () => MpesaConfirmation::query()->create([
        'payment_account_id' => $other->id, 'trans_id' => 'TOTHER1234', 'trans_type' => 'Pay Bill', 'amount_cents' => 78_000,
        'business_short_code' => '174379', 'bill_ref' => $reference, 'paid_at' => now(),
    ])->id);
    (new MatchMpesaConfirmation($this->tenant->id, $id))->handle(app(MatchMpesaCodes::class));

    // And in another business entirely.
    [$elsewhere] = tenantWithBranch();
    $elsewhereAccount = $elsewhere->run(fn () => PaymentAccount::query()->sole());
    $id = $elsewhere->run(fn () => MpesaConfirmation::query()->create([
        'payment_account_id' => $elsewhereAccount->id, 'trans_id' => 'TELSE12345', 'trans_type' => 'Pay Bill', 'amount_cents' => 78_000,
        'business_short_code' => '174379', 'bill_ref' => $reference, 'paid_at' => now(),
    ])->id);
    (new MatchMpesaConfirmation($elsewhere->id, $id))->handle(app(MatchMpesaCodes::class));

    expect(qrPayment($request['uuid'])->status)->toBe(PaymentStatus::Pending)
        ->and($elsewhere->run(fn () => Payment::query()->count()))->toBe(0);
});

it('keeps the payment open when no QR can be made, so the account number printed instead still pays it', function () {
    config(['vendapos.payments.fake' => false]);
    Http::preventStrayRequests();
    Http::fake([
        'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'token', 'expires_in' => '3599']),
        'sandbox.safaricom.co.ke/mpesa/qrcode/v1/generate' => Http::response(['errorCode' => '404.001.03', 'errorMessage' => 'Invalid Access Token'], 400),
    ]);
    $request = qrPaymentOf();

    requestPayment($request)->assertAccepted();

    $payment = qrPayment($request['uuid']);
    expect($payment)->status->toBe(PaymentStatus::Pending)->failure_code->toBe('qr_failed');
    showPayment($request['uuid'])->assertJsonPath('qr.png', null)->assertJsonPath('qr.paybill', '174379')->assertJsonPath('qr.account', $payment->reference);

    confirmPaybill((string) $payment->reference, 78_000);
    expect(qrPayment($request['uuid'])->status)->toBe(PaymentStatus::Succeeded);
});

it('asks Daraja for a paybill QR with the bill’s reference and whole shillings', function () {
    config(['vendapos.payments.fake' => false]);
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'token', 'expires_in' => '3599']),
        'sandbox.safaricom.co.ke/mpesa/qrcode/v1/generate' => Http::response(['ResponseCode' => 'AG_20191219_000043fdf61864fe9ff5', 'ResponseDescription' => 'QR Code Successfully Generated.', 'QRCode' => 'iVBORw0KGgo=']),
    ]);
    $request = qrPaymentOf();

    requestPayment($request)->assertAccepted();
    $payment = qrPayment($request['uuid']);

    expect($payment->qr_png)->toBe('iVBORw0KGgo=');
    Http::assertSent(fn (Request $sent) => str_ends_with($sent->url(), '/mpesa/qrcode/v1/generate')
        && $sent->hasHeader('Authorization', 'Bearer token')
        && $sent['MerchantName'] === 'Mama Oliech Restaurant'
        && $sent['RefNo'] === $payment->reference
        && $sent['Amount'] === 780
        && $sent['TrxCode'] === 'PB'
        && $sent['CPI'] === '174379');
});

it('never asks Safaricom about a QR payment: only its confirmation settles it', function () {
    Queue::fake();
    $request = qrPaymentOf();
    requestPayment($request)->assertAccepted();
    $this->travel(2)->hours();

    $this->artisan(ReconcilePaymentsCommand::class)->assertSuccessful();

    Queue::assertNotPushed(QueryPaymentStatus::class);
});
