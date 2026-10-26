<?php

declare(strict_types=1);

use App\Domain\Payments\Actions\ApplyPaymentOutcome;
use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Events\PaymentCompleted;
use App\Domain\Payments\Gateways\MobileMoneyGateway;
use App\Domain\Payments\Jobs\InitiatePayment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use VendaPOS\Core\Enums\PaymentStatus;

uses(DatabaseMigrations::class);

/** Throws on every call, like a provider that is down. */
final class UnreachableGateway extends MobileMoneyGateway
{
    public function initiate(Payment $payment): GatewayResult
    {
        throw new RuntimeException('Connection timed out');
    }

    public function query(Payment $payment): GatewayResult
    {
        throw new RuntimeException('Connection timed out');
    }

    public function parseCallback(array $body): ?array
    {
        return null;
    }

    public function checkCredentials(array $credentials, string $environment): ?string
    {
        return self::UNREACHABLE;
    }
}

/** Refuses every push, like a provider rejecting a bad shortcode. */
final class RejectingGateway extends MobileMoneyGateway
{
    public function initiate(Payment $payment): GatewayResult
    {
        return GatewayResult::unsuccessful(PaymentStatus::Failed, '400.002.02', 'Invalid BusinessShortCode');
    }

    public function query(Payment $payment): GatewayResult
    {
        return GatewayResult::pending();
    }

    public function parseCallback(array $body): ?array
    {
        return null;
    }

    public function checkCredentials(array $credentials, string $environment): ?string
    {
        return self::KEYS_REFUSED;
    }
}

beforeEach(function () {
    config(['vendapos.payments.fake' => true]);

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('records a pending payment, its callback route and its outbox event, then queues the push', function () {
    Queue::fake();
    $request = paymentOf();

    requestPayment($request)
        ->assertAccepted()
        ->assertJson(['uuid' => $request['uuid'], 'status' => 'pending', 'amount_cents' => 185_000, 'currency' => 'KES'])
        ->assertJsonMissingPath('callback_token');

    $payment = paymentIn($this->tenant, $request['uuid']);
    $route = DB::table('payment_callback_routes')->where('payment_id', $payment->id)->sole();
    $event = DomainEvent::query()->where('aggregate_id', $payment->id)->sole();

    expect($payment->phone)->toBe('+254712345678')
        ->and($payment->branch_id)->toBe($this->branch->id)
        ->and($payment->device_id)->toBe($this->enrolment->device->id)
        ->and($payment->order_id)->toBe($request['order_uuid'])
        ->and($route->tenant_id)->toBe($this->tenant->id)
        ->and($route->token_hash)->toBe(hash('sha256', $payment->callback_token))
        ->and($payment->callbackUrl())->toEndWith("/webhooks/payments/{$payment->callback_token}")
        ->and($event->event_type)->toBe('payment.requested')
        ->and($event->payload)->not->toHaveKey('phone');

    Queue::assertPushedOn('payments', InitiatePayment::class, fn (InitiatePayment $job) => $job->eventId === $event->event_id && $job->tenantId === $this->tenant->id);
});

it('pushes the request to the provider after commit and keeps its request ID', function () {
    $request = paymentOf();

    requestPayment($request)->assertAccepted();

    $payment = paymentIn($this->tenant, $request['uuid']);
    // Date casts need the tenant connection, so read initiated_at inside the tenant.
    $initiatedAt = $this->tenant->run(fn () => Payment::query()->findOrFail($request['uuid'])->initiated_at);

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->provider_request_id)->toBe("fake-{$request['uuid']}")
        ->and($initiatedAt)->not->toBeNull();
});

it('accepts a payment before its order has synced', function () {
    requestPayment(paymentOf(['order_uuid' => null]))->assertAccepted();
});

it('is idempotent: resending the same request returns the payment without a second push', function () {
    Queue::fake();
    $request = paymentOf();

    requestPayment($request)->assertAccepted();
    requestPayment($request)->assertOk()->assertJsonPath('uuid', $request['uuid']);

    expect($this->tenant->run(fn () => Payment::count()))->toBe(1)
        ->and(DomainEvent::count())->toBe(1);
    Queue::assertPushed(InitiatePayment::class, 1);
});

it('refuses to reuse a payment UUID for different details', function () {
    $request = paymentOf();
    requestPayment($request)->assertAccepted();

    requestPayment([...$request, 'amount_cents' => 200_000])->assertConflict();
});

it('rejects amounts with cents, numbers from the wrong country and unknown fields', function (array $overrides, string $field) {
    requestPayment(paymentOf($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($this->tenant->run(fn () => Payment::count()))->toBe(0);
})->with([
    'cents' => [['amount_cents' => 123_450], 'amount_cents'],
    'tanzanian number for M-Pesa Kenya' => [['phone' => '+255712345678'], 'phone'],
    'tenant in the body' => [['tenant_id' => 'someone-else'], 'tenant_id'],
    'unknown provider' => [['provider' => 'mpesa_tz'], 'provider'],
]);

it('shows a payment only to the device that asked for it', function () {
    $request = paymentOf();
    requestPayment($request);

    $show = fn ($enrolment) => deviceRequest('GET', "/api/v1/payments/{$request['uuid']}", $enrolment->plainTextToken, $enrolment->device->id);

    $show($this->enrolment)->assertOk()->assertJsonPath('status', 'pending');
    $show(enrolFor($this->tenant, $this->branch))->assertNotFound();

    [$otherTenant, $otherBranch] = tenantWithBranch();
    $show(enrolFor($otherTenant, $otherBranch))->assertNotFound();
});

it('requires the payments:initiate ability', function () {
    $token = $this->enrolment->device->createToken('sync-only', ['orders:sync'], now()->addHour())->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}", 'X-Device-ID' => $this->enrolment->device->id])
        ->postJson('/api/v1/payments', paymentOf())
        ->assertForbidden();
});

it('fails the payment at once, without retrying, when the provider cannot be reached', function () {
    config(['vendapos.payments.fake' => false, 'vendapos.payments.gateways.mpesa_ke' => UnreachableGateway::class]);
    $request = paymentOf();

    requestPayment($request)->assertAccepted();

    $payment = paymentIn($this->tenant, $request['uuid']);
    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_code)->toBe('initiation_error')
        ->and(DomainEvent::query()->where('aggregate_id', $payment->id)->pluck('event_type')->all())->toBe(['payment.requested', 'payment.failed'])
        ->and((new InitiatePayment('t', 'e'))->tries)->toBe(1);
});

it('records the provider’s reason when it refuses the push', function () {
    config(['vendapos.payments.fake' => false, 'vendapos.payments.gateways.mpesa_ke' => RejectingGateway::class]);
    $request = paymentOf();

    requestPayment($request);

    expect(paymentIn($this->tenant, $request['uuid']))
        ->status->toBe(PaymentStatus::Failed)
        ->failure_code->toBe('400.002.02');
});

it('refuses a provider that has no gateway yet, such as Airtel Money', function () {
    config(['vendapos.payments.fake' => false]);

    requestPayment(paymentOf(['provider' => 'airtel_ke', 'phone' => '0733 123 456']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['provider' => 'airtel_ke is not available yet.']);

    expect($this->tenant->run(fn () => Payment::count()))->toBe(0);
});

it('applies the first final answer only, once', function () {
    Event::fake([PaymentCompleted::class]);
    $request = paymentOf();
    requestPayment($request);

    $apply = fn (GatewayResult $result) => $this->tenant->run(fn () => app(ApplyPaymentOutcome::class)->handle($request['uuid'], $result));

    $apply(GatewayResult::succeeded('TJL4XK9P2Q', 185_000));
    $apply(GatewayResult::succeeded('TJL4XK9P2Q', 185_000));
    $apply(GatewayResult::unsuccessful(PaymentStatus::Cancelled, '1032', 'Request cancelled by user'));

    expect(paymentIn($this->tenant, $request['uuid']))
        ->status->toBe(PaymentStatus::Succeeded)
        ->provider_reference->toBe('TJL4XK9P2Q')
        ->and(DomainEvent::query()->where('aggregate_id', $request['uuid'])->pluck('event_type')->all())->toBe(['payment.requested', 'payment.succeeded']);
    Event::assertDispatchedTimes(PaymentCompleted::class, 1);
});

it('never marks a payment paid when the reported amount differs', function () {
    $request = paymentOf();
    requestPayment($request);

    $this->tenant->run(fn () => app(ApplyPaymentOutcome::class)->handle($request['uuid'], GatewayResult::succeeded('TJL4XK9P2Q', 100)));

    expect(paymentIn($this->tenant, $request['uuid'])->status)->toBe(PaymentStatus::Pending)
        ->and(DomainEvent::query()->where('event_type', 'payment.succeeded')->count())->toBe(0);
});
