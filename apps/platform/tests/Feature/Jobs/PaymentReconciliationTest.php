<?php

declare(strict_types=1);

use App\Domain\Payments\Actions\ApplyPaymentOutcome;
use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Gateways\MobileMoneyGateway;
use App\Domain\Payments\Jobs\InitiatePayment;
use App\Domain\Payments\Jobs\QueryPaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use VendaPOS\Core\Enums\PaymentStatus;

uses(DatabaseMigrations::class);

/** A provider whose status endpoint is down. */
final class StatusEndpointDownGateway extends MobileMoneyGateway
{
    public function initiate(Payment $payment): GatewayResult
    {
        return GatewayResult::accepted('down-'.$payment->id);
    }

    public function generateQr(Payment $payment, string $merchantName): string
    {
        throw new RuntimeException('No QR from this gateway');
    }

    public function query(Payment $payment): GatewayResult
    {
        throw new RuntimeException('503 Service Unavailable');
    }

    public function parseCallback(array $body): ?array
    {
        return null;
    }

    public function registerConfirmationUrls(PaymentAccount $account): ?string
    {
        return null;
    }

    public function checkCredentials(array $credentials, string $environment): ?string
    {
        return null;
    }
}

beforeEach(function () {
    config(['vendapos.payments.fake' => true, 'vendapos.payments.fake_outcome' => 'pending']);
    $this->travelTo('2026-10-21 16:00:00');

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** Push a payment through the fake gateway and return its UUID. */
function pendingPayment(): string
{
    $request = paymentOf();
    requestPayment($request)->assertAccepted();

    return $request['uuid'];
}

function queryStatus(Tenant $tenant, string $paymentId): void
{
    app()->call([new QueryPaymentStatus($tenant->id, $paymentId), 'handle']);
}

it('queues a status query only for overdue pending payments, in every tenant', function () {
    $overdue = pendingPayment();

    [$otherTenant, $otherBranch] = tenantWithBranch();
    $ours = $this->enrolment;
    $this->enrolment = enrolFor($otherTenant, $otherBranch);
    $theirs = pendingPayment();
    $this->enrolment = $ours;

    $settled = pendingPayment();
    $this->tenant->run(fn () => app(ApplyPaymentOutcome::class)->handle($settled, GatewayResult::succeeded('TJL4XK9P2Q', 185_000)));

    $this->travel(2)->minutes();
    $fresh = pendingPayment();

    Queue::fake();
    $this->artisan('payments:reconcile')->assertSuccessful();

    $queued = Queue::pushed(QueryPaymentStatus::class)->map(fn (QueryPaymentStatus $job) => [$job->tenantId, $job->paymentId])->sort()->values()->all();

    expect($queued)->toEqualCanonicalizing([[$this->tenant->id, $overdue], [$otherTenant->id, $theirs]])
        ->and($queued)->not->toContain([$this->tenant->id, $fresh])
        ->and($queued)->not->toContain([$this->tenant->id, $settled]);
    Queue::assertPushedOn('payments', QueryPaymentStatus::class);
});

it('settles a payment from the provider’s answer', function () {
    $payment = pendingPayment();
    config(['vendapos.payments.fake_outcome' => 'succeeded']);
    $this->travel(2)->minutes();

    queryStatus($this->tenant, $payment);

    expect(paymentIn($this->tenant, $payment)->status)->toBe(PaymentStatus::Succeeded)
        ->and(DomainEvent::query()->where('aggregate_id', $payment)->pluck('event_type')->all())->toBe(['payment.requested', 'payment.succeeded']);
});

it('keeps asking while the provider says processing, and times out only after the give-up window', function () {
    $payment = pendingPayment();

    $this->travel(2)->minutes();
    queryStatus($this->tenant, $payment);
    expect(paymentIn($this->tenant, $payment)->status)->toBe(PaymentStatus::Pending);

    $this->travel(10)->minutes();
    queryStatus($this->tenant, $payment);
    expect(paymentIn($this->tenant, $payment))->status->toBe(PaymentStatus::TimedOut)->failure_code->toBe('no_final_status');
});

it('never times a payment out when the provider cannot be asked', function () {
    $payment = pendingPayment();
    config(['vendapos.payments.fake' => false, 'vendapos.payments.gateways.mpesa_ke' => StatusEndpointDownGateway::class]);

    $this->travel(30)->minutes();
    queryStatus($this->tenant, $payment);

    expect(paymentIn($this->tenant, $payment)->status)->toBe(PaymentStatus::Pending);
});

it('fails a payment that was never pushed after the cutoff, and a late push then does nothing', function () {
    Queue::fake();
    $payment = pendingPayment();

    $this->travel(5)->minutes();
    queryStatus($this->tenant, $payment);
    expect(paymentIn($this->tenant, $payment)->status)->toBe(PaymentStatus::Pending);

    $this->travel(6)->minutes();
    queryStatus($this->tenant, $payment);
    expect(paymentIn($this->tenant, $payment))->status->toBe(PaymentStatus::Failed)->failure_code->toBe('never_initiated');

    // The original push job finally runs: the payment is final, so nothing is sent.
    Queue::pushed(InitiatePayment::class)->each(fn (InitiatePayment $job) => $job->handle());
    expect(paymentIn($this->tenant, $payment)->provider_request_id)->toBeNull();
});

it('leaves tenancy as it found it', function () {
    $payment = pendingPayment();
    tenancy()->end();

    queryStatus($this->tenant, $payment);

    expect(tenancy()->initialized)->toBeFalse();
});
