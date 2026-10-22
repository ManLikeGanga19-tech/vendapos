<?php

declare(strict_types=1);

use App\Domain\Analytics\Models\ItemSalesDaily;
use App\Domain\Analytics\Models\SalesHourly;
use App\Domain\Catalogue\Models\Item;
use App\Domain\Etims\Features\EtimsEnabled;
use App\Domain\Etims\Models\EtimsSubmission;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use VendaPOS\Core\Enums\EtimsStatus;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\MobileMoneyProvider;
use VendaPOS\Core\Enums\PaymentStatus;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->nyama = $this->tenant->run(fn () => Item::create([
        'type' => ItemType::Product, 'name' => 'Nyama choma', 'price_cents' => 185_000, 'vat_category' => VatCategory::Standard,
    ]));

    Feature::for(EtimsEnabled::scope($this->branch->id))->activate(EtimsEnabled::class);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('moves no stock, adds no sales and owes eTIMS nothing for a cancelled order', function () {
    $order = saleOf([[$this->nyama, 2]], overrides: ['status' => 'cancelled']);

    sync([$order])->assertJsonPath('results.0.status', 'accepted');

    $this->tenant->run(function () use ($order) {
        expect(StockMovement::query()->count())->toBe(0)
            ->and((int) SalesHourly::query()->sum('order_count'))->toBe(0)
            ->and((int) SalesHourly::query()->sum('cancelled_count'))->toBe(1)
            ->and((int) SalesHourly::query()->sum('total_cents'))->toBe(0)
            ->and(ItemSalesDaily::query()->count())->toBe(0)
            ->and(EtimsSubmission::query()->count())->toBe(0)
            ->and(Order::query()->findOrFail($order['uuid'])->etims_status)->toBe(EtimsStatus::NotRequired);
    });
});

it('flags a mobile-money payment that succeeded for an order later cancelled', function () {
    Log::spy();
    $order = saleOf([[$this->nyama, 1]], overrides: ['status' => 'cancelled']);

    $paymentId = $this->tenant->run(fn () => Payment::query()->create([
        'id' => (string) Str::uuid(),
        'order_id' => $order['uuid'],
        'branch_id' => $this->branch->id,
        'device_id' => $this->enrolment->device->id,
        'provider' => MobileMoneyProvider::MpesaKe,
        'phone' => '+254712345678',
        'amount_cents' => 185_000,
        'currency' => 'KES',
        'status' => PaymentStatus::Succeeded,
        'callback_token' => 'token',
        'provider_reference' => 'TJK1234567',
    ])->id);

    sync([$order]);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $message === 'payments.paid_order_cancelled'
        && $context['payment_id'] === $paymentId
        && $context['tenant_id'] === $this->tenant->id
        && $context['amount_cents'] === 185_000);
});

it('does not flag payments for completed orders', function () {
    Log::spy();

    sync([saleOf([[$this->nyama, 1]])]);

    Log::shouldNotHaveReceived('warning', fn (string $message) => $message === 'payments.paid_order_cancelled');
});
