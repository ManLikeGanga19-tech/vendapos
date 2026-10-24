<?php

declare(strict_types=1);

use App\Domain\Payments\Actions\ApplyPaymentOutcome;
use App\Domain\Payments\Gateways\DarajaGateway;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentCredential;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Webhooks\WebhookReceipt;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\PaymentStatus;

uses(DatabaseMigrations::class);

// Safaricom's public sandbox shortcode and passkey.
const SANDBOX_SHORTCODE = '174379';
const SANDBOX_PASSKEY = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
const SAFARICOM_IP = '196.201.214.200';

beforeEach(function () {
    config(['vendapos.payments.fake' => false]);
    Cache::flush();
    Http::preventStrayRequests();
    $this->travelTo('2026-10-21 16:15:00'); // 19:15 in Nairobi

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);

    $this->tenant->run(fn () => PaymentCredential::create([
        'provider' => 'mpesa_ke',
        'environment' => PaymentCredential::SANDBOX,
        'credentials' => [
            'consumer_key' => 'sandbox-consumer-key',
            'consumer_secret' => 'sandbox-consumer-secret',
            'passkey' => SANDBOX_PASSKEY,
            'shortcode' => SANDBOX_SHORTCODE,
            'party_b' => SANDBOX_SHORTCODE,
            'transaction_type' => 'CustomerPayBillOnline',
        ],
    ]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** Daraja answering OAuth and STK push; $push overrides the push response. */
function fakeDaraja(?array $push = null, int $pushStatus = 200, ?array $query = null, int $queryStatus = 200): void
{
    Http::fake([
        'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'sandbox-access-token', 'expires_in' => '3599']),
        'sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => fn () => Http::response($push ?? [
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_'.bin2hex(random_bytes(10)),
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ], $pushStatus),
        'sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query' => Http::response($query ?? [], $queryStatus),
    ]);
}

/** @return array<string, mixed> Daraja's STK callback body. */
function stkCallback(string $checkoutRequestId, int $resultCode = 0, int|float $amount = 1850, string $receipt = 'TJL4XK9P2Q'): array
{
    $callback = [
        'MerchantRequestID' => '29115-34620561-1',
        'CheckoutRequestID' => $checkoutRequestId,
        'ResultCode' => $resultCode,
        'ResultDesc' => $resultCode === 0 ? 'The service request is processed successfully.' : 'Request cancelled by user',
    ];

    if ($resultCode === 0) {
        $callback['CallbackMetadata'] = ['Item' => [
            ['Name' => 'Amount', 'Value' => $amount],
            ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
            ['Name' => 'TransactionDate', 'Value' => 20261021191530],
            ['Name' => 'PhoneNumber', 'Value' => 254712345678],
        ]];
    }

    return ['Body' => ['stkCallback' => $callback]];
}

function postCallback(Payment $payment, array $body, string $ip = SAFARICOM_IP, ?string $token = null): TestResponse
{
    $token ??= test()->tenant->run(fn () => Payment::query()->findOrFail($payment->id)->callback_token);

    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson("/webhooks/payments/mpesa_ke/{$token}", $body);
}

/** A payment pushed through Daraja, with its CheckoutRequestID stored. */
function pushedPayment(?Tenant $tenant = null): Payment
{
    $request = paymentOf();
    requestPayment($request)->assertAccepted();

    return paymentIn($tenant ?? test()->tenant, $request['uuid']);
}

it('sends the STK push with Daraja’s fields and keeps the CheckoutRequestID', function () {
    fakeDaraja();
    $payment = pushedPayment();

    expect($payment->provider_request_id)->toStartWith('ws_CO_');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/oauth/v1/generate')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('sandbox-consumer-key:sandbox-consumer-secret')));

    Http::assertSent(function (Request $request) use ($payment) {
        if (! str_ends_with($request->url(), '/mpesa/stkpush/v1/processrequest')) {
            return false;
        }

        return $request->hasHeader('Authorization', 'Bearer sandbox-access-token')
            && $request['BusinessShortCode'] === SANDBOX_SHORTCODE
            && $request['Timestamp'] === '20261021191500'
            && $request['Password'] === base64_encode(SANDBOX_SHORTCODE.SANDBOX_PASSKEY.'20261021191500')
            && $request['TransactionType'] === 'CustomerPayBillOnline'
            && $request['Amount'] === 1850
            && $request['PartyA'] === '254712345678'
            && $request['PhoneNumber'] === '254712345678'
            && $request['PartyB'] === SANDBOX_SHORTCODE
            && strlen($request['AccountReference']) <= 12
            && str_ends_with($request['CallBackURL'], '/webhooks/payments/mpesa_ke/'.test()->tenant->run(fn () => Payment::query()->findOrFail($payment->id)->callback_token));
    });
});

it('reuses the OAuth token across calls', function () {
    fakeDaraja(query: ['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'ok']);
    $payment = pushedPayment();
    $oauthCalls = fn () => Http::recorded(fn (Request $request) => str_contains($request->url(), '/oauth/'))->count();

    // The test cache is per tenant initialisation (array driver); in production it is Redis.
    $before = $oauthCalls();
    $this->tenant->run(function () use ($payment) {
        app(DarajaGateway::class)->query($payment);
        app(DarajaGateway::class)->query($payment);
        app(DarajaGateway::class)->query($payment);
    });

    expect($oauthCalls() - $before)->toBe(1);
});

it('fails the payment with Daraja’s reason when the push is refused', function () {
    fakeDaraja(['requestId' => '1234-5678', 'errorCode' => '400.002.02', 'errorMessage' => 'Bad Request - Invalid BusinessShortCode'], 400);

    expect(pushedPayment())
        ->status->toBe(PaymentStatus::Failed)
        ->failure_code->toBe('400.002.02')
        ->failure_reason->toBe('Bad Request - Invalid BusinessShortCode');
});

it('fails the payment when Daraja errors or the business has no M-Pesa keys', function (Closure $setUp) {
    $setUp();

    expect(pushedPayment())->status->toBe(PaymentStatus::Failed)->failure_code->toBe('initiation_error');
})->with([
    'server error' => [fn () => fakeDaraja(['errorMessage' => 'Internal error'], 500)],
    'no credentials' => [function () {
        fakeDaraja();
        test()->tenant->run(fn () => PaymentCredential::query()->delete());
    }],
]);

it('marks the payment paid from Safaricom’s callback and keeps the raw body', function () {
    fakeDaraja();
    $payment = pushedPayment();
    $body = stkCallback($payment->provider_request_id);

    postCallback($payment, $body)->assertOk()->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

    $receipt = WebhookReceipt::query()->sole();

    expect(paymentIn($this->tenant, $payment->id))
        ->status->toBe(PaymentStatus::Succeeded)
        ->provider_reference->toBe('TJL4XK9P2Q')
        ->and($receipt->status)->toBe(WebhookReceipt::PROCESSED)
        ->and($receipt->tenant_id)->toBe($this->tenant->id)
        ->and(json_decode($receipt->body, true))->toBe($body);
});

it('records cancellations and timeouts', function (int $code, PaymentStatus $status) {
    fakeDaraja();
    $payment = pushedPayment();

    postCallback($payment, stkCallback($payment->provider_request_id, $code));

    expect(paymentIn($this->tenant, $payment->id))->status->toBe($status)->failure_code->toBe((string) $code);
})->with([
    'cancelled by the customer' => [1032, PaymentStatus::Cancelled],
    'phone unreachable' => [1037, PaymentStatus::TimedOut],
    'insufficient balance' => [1, PaymentStatus::Failed],
]);

it('ignores callbacks from outside Safaricom’s addresses', function () {
    fakeDaraja();
    $payment = pushedPayment();

    postCallback($payment, stkCallback($payment->provider_request_id), ip: '203.0.113.9')
        ->assertOk()->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

    expect(paymentIn($this->tenant, $payment->id)->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookReceipt::query()->sole())->status->toBe(WebhookReceipt::REJECTED)->note->toBe('ip_not_allowed');
});

it('gives an unknown token the same reply and changes nothing', function () {
    fakeDaraja();
    $payment = pushedPayment();

    postCallback($payment, stkCallback($payment->provider_request_id), token: str_repeat('a', 48))
        ->assertOk()->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

    expect(paymentIn($this->tenant, $payment->id)->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookReceipt::query()->sole())->note->toBe('unknown_token')->tenant_id->toBeNull();
});

it('rejects a callback whose CheckoutRequestID is not this payment’s', function () {
    fakeDaraja();
    $payment = pushedPayment();

    postCallback($payment, stkCallback('ws_CO_someone_elses_request'));

    expect(paymentIn($this->tenant, $payment->id)->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookReceipt::query()->sole()->note)->toBe('request_id_mismatch');
});

it('never marks a payment paid when the callback reports a different amount', function () {
    fakeDaraja();
    $payment = pushedPayment();

    postCallback($payment, stkCallback($payment->provider_request_id, amount: 1));

    expect(paymentIn($this->tenant, $payment->id)->status)->toBe(PaymentStatus::Pending);
});

it('applies a replayed callback once', function () {
    fakeDaraja();
    $payment = pushedPayment();
    $body = stkCallback($payment->provider_request_id);

    postCallback($payment, $body);
    postCallback($payment, $body);

    expect(DomainEvent::query()->where('event_type', 'payment.succeeded')->count())->toBe(1);
});

it('keeps one tenant’s callback token away from another tenant’s payment', function () {
    fakeDaraja();
    $ours = pushedPayment();

    [$otherTenant, $otherBranch] = tenantWithBranch();
    $otherTenant->run(fn () => PaymentCredential::create(['provider' => 'mpesa_ke', 'environment' => 'sandbox', 'credentials' => $this->tenant->run(fn () => PaymentCredential::query()->sole()->credentials)]));
    $this->enrolment = enrolFor($otherTenant, $otherBranch);
    $theirs = pushedPayment($otherTenant);

    // Our token, their CheckoutRequestID.
    postCallback($ours, stkCallback($theirs->provider_request_id));

    expect(paymentIn($otherTenant, $theirs->id)->status)->toBe(PaymentStatus::Pending)
        ->and(paymentIn($this->tenant, $ours->id)->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookReceipt::query()->sole())->tenant_id->toBe($this->tenant->id)->note->toBe('request_id_mismatch');
});

it('reads STK status queries: still processing, paid, unreachable', function (array $response, int $status, PaymentStatus $expected) {
    fakeDaraja(query: $response, queryStatus: $status);
    $payment = pushedPayment();

    $result = $this->tenant->run(fn () => app(DarajaGateway::class)->query($payment));

    expect($result->status)->toBe($expected);
})->with([
    'processing' => [['requestId' => '1', 'errorCode' => '500.001.1001', 'errorMessage' => 'The transaction is being processed'], 500, PaymentStatus::Pending],
    'paid' => [['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'The service request is processed successfully.'], 200, PaymentStatus::Succeeded],
    'unreachable' => [['ResponseCode' => '0', 'ResultCode' => '1037', 'ResultDesc' => 'DS timeout user cannot be reached'], 200, PaymentStatus::TimedOut],
]);

it('adds the receipt number from a late callback to a payment a status query already confirmed', function () {
    fakeDaraja(query: ['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'ok']);
    $payment = pushedPayment();

    $this->tenant->run(fn () => app(ApplyPaymentOutcome::class)->handle($payment->id, app(DarajaGateway::class)->query($payment)));
    postCallback($payment, stkCallback($payment->provider_request_id));

    expect(paymentIn($this->tenant, $payment->id))->status->toBe(PaymentStatus::Succeeded)->provider_reference->toBe('TJL4XK9P2Q')
        ->and(DomainEvent::query()->where('event_type', 'payment.succeeded')->count())->toBe(1);
});

it('stores keys encrypted and takes them from prompts, never arguments', function () {
    $this->artisan('payments:credentials', ['tenant' => $this->tenant->id, 'provider' => 'mpesa_ke', '--environment' => 'production'])
        ->expectsQuestion('consumer_key', 'live-consumer-key')
        ->expectsQuestion('consumer_secret', 'live-consumer-secret')
        ->expectsQuestion('passkey', 'live-passkey-0f9e8d7c6b5a')
        ->expectsQuestion('shortcode', '600000')
        ->expectsQuestion('party_b', '600000')
        ->expectsQuestion('transaction_type', 'CustomerBuyGoodsOnline')
        ->assertSuccessful();

    $raw = $this->tenant->run(fn () => DB::table('payment_credentials')->value('credentials'));
    $stored = $this->tenant->run(fn () => PaymentCredential::query()->sole());

    expect($raw)->not->toContain('live-passkey-0f9e8d7c6b5a')
        ->and($stored->environment)->toBe('production')
        ->and($stored->credentials['passkey'])->toBe('live-passkey-0f9e8d7c6b5a');
});

it('refuses a transaction type daraja does not accept and keeps the saved keys', function () {
    $this->artisan('payments:credentials', ['tenant' => $this->tenant->id, 'provider' => 'mpesa_ke'])
        ->expectsQuestion('consumer_key', 'new-consumer-key')
        ->expectsQuestion('consumer_secret', 'new-consumer-secret')
        ->expectsQuestion('passkey', 'new-passkey')
        ->expectsQuestion('shortcode', '174379')
        ->expectsQuestion('party_b', '174379')
        ->expectsQuestion('transaction_type', 'Test')
        ->expectsOutputToContain('transaction_type must be one of: CustomerPayBillOnline, CustomerBuyGoodsOnline')
        ->assertFailed();

    expect($this->tenant->run(fn () => PaymentCredential::query()->sole())->credentials)
        ->consumer_key->toBe('sandbox-consumer-key')
        ->transaction_type->toBe('CustomerPayBillOnline');
});

it('accepts Safaricom’s callback when it arrives through Cloudflare', function () {
    fakeDaraja();
    $payment = pushedPayment();
    $token = $this->tenant->run(fn () => Payment::query()->findOrFail($payment->id)->callback_token);

    $this->withServerVariables(['REMOTE_ADDR' => '162.158.20.5']) // a Cloudflare edge
        ->postJson("/webhooks/payments/mpesa_ke/{$token}", stkCallback($payment->provider_request_id), ['CF-Connecting-IP' => SAFARICOM_IP])
        ->assertOk();

    expect(paymentIn($this->tenant, $payment->id)->status)->toBe(PaymentStatus::Succeeded);
});

it('refuses a callback that claims a Safaricom IP in headers on a direct connection', function () {
    fakeDaraja();
    $payment = pushedPayment();
    $token = $this->tenant->run(fn () => Payment::query()->findOrFail($payment->id)->callback_token);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->postJson("/webhooks/payments/mpesa_ke/{$token}", stkCallback($payment->provider_request_id), [
            'CF-Connecting-IP' => SAFARICOM_IP,
            'X-Forwarded-For' => SAFARICOM_IP,
        ])
        ->assertOk();

    expect(paymentIn($this->tenant, $payment->id)->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookReceipt::query()->sole())->status->toBe(WebhookReceipt::REJECTED)->note->toBe('ip_not_allowed');
});
