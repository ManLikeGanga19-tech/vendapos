<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Orders\Events\OrderRecorded;
use App\Domain\Orders\Events\OrderVoided;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderTender;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\MobileMoneyProvider;
use VendaPOS\Core\Enums\PaymentStatus;
use VendaPOS\Core\Enums\PaymentType;
use VendaPOS\Core\Enums\TenderMethod;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Event::fake([OrderRecorded::class, OrderVoided::class]);

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->nyama = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Nyama choma', 'price_cents' => 185_000, 'vat_category' => VatCategory::Standard]));
    // Cents in the price: M-Pesa pays the shillings, cash the 50 cents.
    $this->soda = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Soda', 'price_cents' => 9_950, 'vat_category' => VatCategory::Standard]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** A push payment in $tenant, started by $deviceId. */
function tenderPayment(Tenant $tenant, string $deviceId, int $amountCents, PaymentStatus $status = PaymentStatus::Succeeded, ?string $orderId = null): string
{
    return $tenant->run(fn () => Payment::create([
        'id' => (string) Str::uuid(),
        'order_id' => $orderId,
        'branch_id' => (string) Str::uuid(),
        'device_id' => $deviceId,
        'provider' => MobileMoneyProvider::MpesaKe,
        'phone' => '+254712345678',
        'amount_cents' => $amountCents,
        'currency' => 'KES',
        'status' => $status,
        'callback_token' => Str::random(48),
    ])->id);
}

/** @return array<string, mixed> */
function pushTender(string $paymentId, int $amount): array
{
    return ['method' => 'mpesa_push', 'amount_cents' => $amount, 'payment_uuid' => $paymentId];
}

it('records cash with the change given, and derives the payment type', function () {
    $sale = saleOf([[$this->nyama, 1]], overrides: ['tenders' => [cashTender(185_000, tendered: 200_000)]]);

    sync([$sale])->assertJsonPath('results.0.status', 'accepted');

    [$order, $tenders] = $this->tenant->run(fn () => [Order::findOrFail($sale['uuid']), OrderTender::query()->get()]);

    expect($order->payment_type)->toBe(PaymentType::Cash)
        ->and($tenders)->toHaveCount(1)
        ->and($tenders->first())
        ->method->toBe(TenderMethod::Cash)
        ->amount_cents->toBe(185_000)
        ->tendered_cents->toBe(200_000)
        ->change_cents->toBe(15_000);
});

it('records a split sale: M-Pesa pays the shillings and cash the cents', function () {
    $paymentId = tenderPayment($this->tenant, $this->enrolment->device->id, 9_900);
    $sale = saleOf([[$this->soda, 1]], overrides: ['tenders' => [pushTender($paymentId, 9_900), cashTender(50)]]);

    sync([$sale])->assertJsonPath('results.0.status', 'accepted');

    [$order, $tenders] = $this->tenant->run(fn () => [Order::findOrFail($sale['uuid']), OrderTender::query()->orderBy('position')->get()]);

    expect($order->payment_type)->toBe(PaymentType::Split)
        ->and($tenders->pluck('payment_id')->all())->toBe([$paymentId, null])
        ->and($tenders->pluck('amount_cents')->all())->toBe([9_900, 50]);
});

it('records a typed M-Pesa code as given', function () {
    $sale = saleOf([[$this->nyama, 1]], overrides: ['tenders' => [['method' => 'mpesa_code', 'amount_cents' => 185_000, 'mpesa_reference' => 'SJ12ABC3DE']]]);

    sync([$sale])->assertJsonPath('results.0.status', 'accepted');

    expect($this->tenant->run(fn () => OrderTender::query()->sole()))
        ->method->toBe(TenderMethod::MpesaCode)
        ->mpesa_reference->toBe('SJ12ABC3DE');
});

it('puts the tenders in the outbox event', function () {
    $sale = saleOf([[$this->nyama, 1]], overrides: ['tenders' => [cashTender(185_000, tendered: 190_000)]]);

    sync([$sale]);

    $payload = DomainEvent::query()->where('aggregate_id', $sale['uuid'])->sole()->payload;

    // jsonb keeps its own key order, so compare the fields, not their order.
    expect($payload['payment_type'])->toBe('cash')
        ->and($payload['tenders'])->toHaveCount(1)
        ->and($payload['tenders'][0])->toEqual(['method' => 'cash', 'amount_cents' => 185_000, 'tendered_cents' => 190_000, 'change_cents' => 5_000, 'payment_id' => null, 'mpesa_reference' => null]);
});

it('records a cancelled sale with no tenders and no payment type', function () {
    $sale = saleOf([[$this->nyama, 1]], overrides: ['status' => 'cancelled']);

    sync([$sale])->assertJsonPath('results.0.status', 'accepted');

    expect($this->tenant->run(fn () => [Order::findOrFail($sale['uuid'])->payment_type, OrderTender::query()->count()]))->toBe([null, 0]);
});

it('rejects tenders that do not pay the sale exactly, and writes nothing', function (array $tenders) {
    $sale = saleOf([[$this->soda, 1]], overrides: ['tenders' => $tenders]);

    sync([$sale])
        ->assertJsonPath('results.0.status', 'rejected')
        ->assertJsonPath('results.0.reason', 'tenders_mismatch');

    expect($this->tenant->run(fn () => [Order::query()->count(), OrderTender::query()->count()]))->toBe([0, 0])
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'short' => [[cashTender(9_900)]],
    'over' => [[cashTender(10_000)]],
    'none' => [[]],
    'M-Pesa cents' => [[['method' => 'mpesa_code', 'amount_cents' => 9_950, 'mpesa_reference' => 'SJ12ABC3DE']]],
    'wrong change' => [[['method' => 'cash', 'amount_cents' => 9_950, 'tendered_cents' => 10_000, 'change_cents' => 0]]],
]);

it('rejects a push tender whose payment is not a fit', function (Closure $payment) {
    $paymentId = $payment($this);
    $sale = saleOf([[$this->nyama, 1]], overrides: ['tenders' => [pushTender($paymentId, 185_000)]]);

    sync([$sale])
        ->assertJsonPath('results.0.status', 'rejected')
        ->assertJsonPath('results.0.reason', 'payment_mismatch')
        ->assertJsonStructure(['results' => [['errors' => ['tenders.0.payment_uuid']]]]);

    expect($this->tenant->run(fn () => Order::query()->count()))->toBe(0);
})->with([
    'unknown' => [fn ($t) => (string) Str::uuid()],
    'another till’s' => [fn ($t) => tenderPayment($t->tenant, (string) Str::uuid(), 185_000)],
    'a different amount' => [fn ($t) => tenderPayment($t->tenant, $t->enrolment->device->id, 180_000)],
    'still pending' => [fn ($t) => tenderPayment($t->tenant, $t->enrolment->device->id, 185_000, PaymentStatus::Pending)],
    'failed' => [fn ($t) => tenderPayment($t->tenant, $t->enrolment->device->id, 185_000, PaymentStatus::Failed)],
    'requested for another sale' => [fn ($t) => tenderPayment($t->tenant, $t->enrolment->device->id, 185_000, orderId: (string) Str::uuid())],
]);

it('lets one payment pay one sale only', function () {
    $paymentId = tenderPayment($this->tenant, $this->enrolment->device->id, 185_000);

    sync([saleOf([[$this->nyama, 1]], sequence: 1, overrides: ['tenders' => [pushTender($paymentId, 185_000)]])])
        ->assertJsonPath('results.0.status', 'accepted');

    sync([saleOf([[$this->nyama, 1]], sequence: 2, overrides: ['tenders' => [pushTender($paymentId, 185_000)]])])
        ->assertJsonPath('results.0.reason', 'payment_mismatch')
        ->assertJsonPath('results.0.errors', ['tenders.0.payment_uuid' => 'The payment already pays another sale.']);
});

it('keeps a resent sale’s tenders as first recorded', function () {
    $paymentId = tenderPayment($this->tenant, $this->enrolment->device->id, 185_000, orderId: null);
    $sale = saleOf([[$this->nyama, 1]], overrides: ['tenders' => [pushTender($paymentId, 185_000)]]);

    sync([$sale])->assertJsonPath('results.0.status', 'accepted');
    sync([$sale])->assertJsonPath('results.0.status', 'duplicate');
    sync([[...$sale, 'status' => 'voided']])->assertJsonPath('results.0.status', 'updated');

    expect($this->tenant->run(fn () => OrderTender::query()->pluck('payment_id')->all()))->toBe([$paymentId]);
});

it('never lets a till claim another tenant’s payment, and reveals nothing about it', function () {
    [$otherTenant, $otherBranch] = tenantWithBranch();
    $other = enrolFor($otherTenant, $otherBranch);
    // The same device ID cannot be faked across tenants, but even the payment's own UUID leads nowhere.
    $theirs = tenderPayment($otherTenant, $other->device->id, 185_000);

    sync([saleOf([[$this->nyama, 1]], overrides: ['tenders' => [pushTender($theirs, 185_000)]])])
        ->assertJsonPath('results.0.reason', 'payment_mismatch')
        ->assertJsonPath('results.0.errors', ['tenders.0.payment_uuid' => 'No payment of this till has this UUID.']);

    expect($this->tenant->run(fn () => Order::query()->count()))->toBe(0)
        ->and($otherTenant->run(fn () => Payment::query()->findOrFail($theirs)->status))->toBe(PaymentStatus::Succeeded);
});

it('rejects the old payment_type key, which the server derives, and unknown tender fields', function () {
    $sale = [...saleOf([[$this->nyama, 1]]), 'payment_type' => 'cash'];
    $sale['tenders'][0]['tip_cents'] = 1_000;

    sync([$sale])
        ->assertJsonPath('results.0.reason', 'invalid')
        ->assertJsonStructure(['results' => [['errors' => ['payment_type', 'tenders.0.tip_cents']]]]);

    expect($this->tenant->run(fn () => Order::query()->count()))->toBe(0);
});
