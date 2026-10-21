<?php

declare(strict_types=1);

use App\Domain\Analytics\Jobs\UpdateSalesSummaries;
use App\Domain\Analytics\Models\DailySales;
use App\Domain\Catalogue\Models\Item;
use App\Domain\Inventory\Jobs\DecrementStock;
use App\Domain\Inventory\Jobs\RestoreStock;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Orders\Actions\RecordOrder;
use App\Domain\Orders\Data\DeviceContext;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use VendaPOS\Core\Data\OrderData;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);

    [$this->nyama, $this->water, $this->shave] = $this->tenant->run(fn () => [
        Item::create(['type' => ItemType::Product, 'name' => 'Nyama choma', 'price_cents' => 185_000, 'vat_category' => VatCategory::Standard]),
        Item::create(['type' => ItemType::Product, 'name' => 'Water 500 ml', 'price_cents' => 5_000, 'vat_category' => VatCategory::ZeroRated]),
        Item::create(['type' => ItemType::Service, 'name' => 'Shave', 'price_cents' => 30_000, 'vat_category' => VatCategory::Standard]),
    ]);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** @return array<string, int> item name => on-hand quantity */
function stockIn(Tenant $tenant): array
{
    return $tenant->run(fn () => StockLevel::query()
        ->join('items', 'items.id', '=', 'stock_levels.item_id')
        ->orderBy('items.name')
        ->pluck('stock_levels.quantity', 'items.name')
        ->all());
}

/** @return list<array<string, mixed>> */
function dailySalesIn(Tenant $tenant): array
{
    return $tenant->run(fn () => DailySales::query()->orderBy('business_date')->get()
        ->map(fn (DailySales $day) => [
            'date' => $day->business_date->toDateString(),
            'orders' => $day->order_count,
            'voids' => $day->void_count,
            'total' => $day->total_cents,
            'vat' => $day->vat_cents,
        ])->all());
}

function eventIdFor(string $orderId, string $type): string
{
    return DomainEvent::query()->where('aggregate_id', $orderId)->where('event_type', $type)->sole()->event_id;
}

it('takes sold products out of the branch’s stock, leaves services alone and lets stock go negative', function () {
    sync([saleOf([[$this->nyama, 2], [$this->water, 1], [$this->shave, 1], [$this->nyama, 1]])])
        ->assertJsonPath('results.0.status', 'accepted');

    expect(stockIn($this->tenant))->toBe(['Nyama choma' => -3, 'Water 500 ml' => -1])
        ->and($this->tenant->run(fn () => StockLevel::query()->negative()->count()))->toBe(2)
        ->and($this->tenant->run(fn () => (int) StockMovement::query()->where('reason', 'sale')->sum('quantity')))->toBe(-4);
});

it('adds each sale to its branch’s Africa/Nairobi business day', function () {
    sync([
        saleOf([[$this->nyama, 1]], sequence: 1, overrides: ['placed_at' => '2026-10-21T23:30:00+03:00']),
        // 21:30 UTC on the 21st is already 00:30 on the 22nd in Nairobi.
        saleOf([[$this->water, 1]], sequence: 2, overrides: ['placed_at' => '2026-10-21T21:30:00Z']),
    ]);

    expect(dailySalesIn($this->tenant))->toBe([
        ['date' => '2026-10-21', 'orders' => 1, 'voids' => 0, 'total' => 185_000, 'vat' => 25_517],
        ['date' => '2026-10-22', 'orders' => 1, 'voids' => 0, 'total' => 5_000, 'vat' => 0],
    ]);
});

it('puts stock back and takes the sale out of the day’s figures when an order is voided', function () {
    $sale = saleOf([[$this->nyama, 2]]);

    sync([$sale]);
    sync([[...$sale, 'status' => 'voided']])->assertJsonPath('results.0.status', 'updated');

    expect(stockIn($this->tenant))->toBe(['Nyama choma' => 0])
        ->and(dailySalesIn($this->tenant))->toBe([['date' => '2026-10-21', 'orders' => 0, 'voids' => 1, 'total' => 0, 'vat' => 0]])
        ->and($this->tenant->run(fn () => StockMovement::query()->orderBy('id')->pluck('reason')->all()))->toBe(['sale', 'void']);
});

it('nets to zero when a void’s jobs run before its sale’s jobs', function () {
    Queue::fake();
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);
    sync([[...$sale, 'status' => 'voided']]);

    $voidJobs = [...Queue::pushed(RestoreStock::class), ...Queue::pushed(UpdateSalesSummaries::class)->filter(
        fn (UpdateSalesSummaries $job) => $job->eventId === eventIdFor($sale['uuid'], 'order.voided'),
    )];
    $saleJobs = [...Queue::pushed(DecrementStock::class), ...Queue::pushed(UpdateSalesSummaries::class)->filter(
        fn (UpdateSalesSummaries $job) => $job->eventId === eventIdFor($sale['uuid'], 'order.recorded'),
    )];

    foreach ([...$voidJobs, ...$saleJobs] as $job) {
        $job->handle();
    }

    expect(stockIn($this->tenant))->toBe(['Nyama choma' => 0])
        ->and(dailySalesIn($this->tenant))->toBe([['date' => '2026-10-21', 'orders' => 0, 'voids' => 1, 'total' => 0, 'vat' => 0]]);
});

it('does nothing when a job is run again for the same event', function () {
    Queue::fake();
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);
    $eventId = eventIdFor($sale['uuid'], 'order.recorded');

    foreach (range(1, 3) as $attempt) {
        (new DecrementStock($this->tenant->id, $eventId))->handle();
        (new UpdateSalesSummaries($this->tenant->id, $eventId))->handle();
    }

    expect(stockIn($this->tenant))->toBe(['Nyama choma' => -1])
        ->and(dailySalesIn($this->tenant)[0]['orders'])->toBe(1)
        ->and($this->tenant->run(fn () => DB::table('processed_events')->where('event_id', $eventId)->pluck('handler')->sort()->values()->all()))
        ->toBe([UpdateSalesSummaries::class, DecrementStock::class]);
});

it('queues each side effect on its own queue, carrying the tenant and outbox event', function () {
    Queue::fake();
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);
    $eventId = eventIdFor($sale['uuid'], 'order.recorded');

    $carries = fn ($job) => $job->tenantId === $this->tenant->id && $job->eventId === $eventId;

    Queue::assertPushedOn('inventory', DecrementStock::class, $carries);
    Queue::assertPushedOn('analytics', UpdateSalesSummaries::class, $carries);
    Queue::assertNotPushed(RestoreStock::class);
});

it('queues nothing for an order whose transaction rolled back', function () {
    Queue::fake();
    // tenant_id is varchar(255): an over-long value makes the outbox insert fail mid-transaction.
    $context = new DeviceContext(str_repeat('x', 300), $this->branch->id, $this->enrolment->device->id);

    expect(fn () => $this->tenant->run(fn () => app(RecordOrder::class)->handle(OrderData::from(saleOf([[$this->nyama, 1]])), $context)))
        ->toThrow(QueryException::class);

    Queue::assertNothingPushed();
});

it('keeps side effects inside the event’s tenant', function () {
    [$otherTenant] = tenantWithBranch();
    Queue::fake();
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);
    $eventId = eventIdFor($sale['uuid'], 'order.recorded');

    // A job that names the wrong tenant for its event refuses to run.
    expect(fn () => (new DecrementStock($otherTenant->id, $eventId))->handle())->toThrow(LogicException::class);

    foreach ([...Queue::pushed(DecrementStock::class), ...Queue::pushed(UpdateSalesSummaries::class)] as $job) {
        $job->handle();
    }

    expect(stockIn($this->tenant))->toBe(['Nyama choma' => -1])
        ->and(stockIn($otherTenant))->toBe([])
        ->and(dailySalesIn($otherTenant))->toBe([])
        ->and($otherTenant->run(fn () => DB::table('processed_events')->count()))->toBe(0);
});

it('leaves tenancy as it found it', function () {
    Queue::fake();
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);
    tenancy()->end();

    (new DecrementStock($this->tenant->id, eventIdFor($sale['uuid'], 'order.recorded')))->handle();

    expect(tenancy()->initialized)->toBeFalse();
});
