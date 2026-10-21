<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Orders\Actions\RecordOrder;
use App\Domain\Orders\Data\DeviceContext;
use App\Domain\Orders\Events\OrderRecorded;
use App\Domain\Orders\Events\OrderVoided;
use App\Domain\Orders\Models\Order;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Data\OrderData;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\Tax\OrderTotals;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Event::fake([OrderRecorded::class, OrderVoided::class]);

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->enrolment = enrolFor($this->tenant, $this->branch);
    $this->nyama = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Nyama choma', 'price_cents' => 185_000, 'vat_category' => VatCategory::Standard]));
    $this->water = $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Water 500 ml', 'price_cents' => 5_000, 'vat_category' => VatCategory::ZeroRated]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/**
 * A correct order payload whose totals are computed with the shared core maths.
 *
 * @param  list<array{0: Item, 1: int}>  $lines
 * @return array<string, mixed>
 */
function saleOf(array $lines, int $sequence = 1, array $overrides = []): array
{
    $lineData = array_map(fn (array $line) => [
        'item_id' => $line[0]->id,
        'name' => $line[0]->name,
        'quantity' => $line[1],
        'unit_price_cents' => $line[0]->price_cents,
        'vat_category' => $line[0]->vat_category->value,
        'line_total_cents' => $line[0]->price_cents * $line[1],
    ], $lines);

    $totals = OrderTotals::fromLines(array_map(fn (array $l) => OrderLineData::from($l), $lineData));

    return array_merge([
        'uuid' => (string) Str::uuid(),
        'sequence_number' => $sequence,
        'placed_at' => '2026-10-21T19:15:00+03:00',
        'status' => 'completed',
        'lines' => $lineData,
        'payment_type' => 'cash',
        'subtotal_cents' => $totals->subtotal->cents,
        'vat_cents' => $totals->vat->cents,
        'total_cents' => $totals->total->cents,
    ], $overrides);
}

/** POST a batch as the test's enrolled device. */
function sync(array $orders): TestResponse
{
    $enrolment = test()->enrolment;
    app('auth')->forgetGuards();

    return test()->withHeaders([
        'Authorization' => 'Bearer '.$enrolment->plainTextToken,
        'X-Device-ID' => $enrolment->device->id,
    ])->postJson('/api/v1/orders/sync', ['orders' => $orders]);
}

it('records an order, its lines and its outbox event in the device’s tenant', function () {
    $sale = saleOf([[$this->nyama, 1], [$this->water, 2]]);

    sync([$sale])->assertOk()->assertExactJson(['results' => [['uuid' => $sale['uuid'], 'status' => 'accepted']]]);

    // Read inside the tenant: the model's connection only exists there.
    $order = $this->tenant->run(function () use ($sale) {
        $order = Order::with('lines')->findOrFail($sale['uuid']);

        return [
            'branch_id' => $order->branch_id,
            'device_id' => $order->device_id,
            'total' => $order->total_cents,
            'vat' => $order->vat_cents,
            'subtotal' => $order->subtotal_cents,
            'line_vat' => $order->lines->pluck('vat_cents')->all(),
            'placed_at' => $order->placed_at->toIso8601String(),
        ];
    });

    expect($order)->toBe([
        'branch_id' => $this->branch->id,
        'device_id' => $this->enrolment->device->id,
        'total' => 195_000,
        'vat' => 25_517,            // only the KES 1,850 standard-rated line carries VAT
        'subtotal' => 169_483,
        'line_vat' => [25_517, 0],
        'placed_at' => '2026-10-21T16:15:00+00:00',   // 19:15 EAT stored as UTC
    ]);

    $event = DomainEvent::query()->where('aggregate_id', $sale['uuid'])->sole();

    expect($event->event_type)->toBe('order.recorded')
        ->and($event->tenant_id)->toBe($this->tenant->id)
        ->and($event->branch_id)->toBe($this->branch->id)
        ->and($event->payload['total_cents'])->toBe(195_000);

    Event::assertDispatched(OrderRecorded::class, fn (OrderRecorded $e) => $e->eventId === $event->event_id && $e->orderId === $sale['uuid']);
});

it('is idempotent: resending an order records nothing new', function () {
    $sale = saleOf([[$this->nyama, 1]]);

    sync([$sale])->assertJsonPath('results.0.status', 'accepted');
    sync([$sale])->assertJsonPath('results.0.status', 'duplicate');

    expect($this->tenant->run(fn () => Order::count()))->toBe(1)
        ->and(DomainEvent::count())->toBe(1);
    Event::assertDispatchedTimes(OrderRecorded::class, 1);
});

it('rejects an order whose totals don’t match while accepting the rest of the batch', function () {
    $good = saleOf([[$this->nyama, 1]], sequence: 1);
    $bad = saleOf([[$this->nyama, 2]], sequence: 2, overrides: ['vat_cents' => 1]);

    $response = sync([$good, $bad])->assertOk();

    expect($response->json('results.0.status'))->toBe('accepted')
        ->and($response->json('results.1.status'))->toBe('rejected')
        ->and($response->json('results.1.reason'))->toBe('totals_mismatch')
        ->and($response->json('results.1.errors'))->toHaveKey('vat_cents')
        ->and($this->tenant->run(fn () => Order::pluck('id')->all()))->toBe([$good['uuid']]);
});

it('rejects items that are not in the tenant’s catalogue', function () {
    [$otherTenant] = tenantWithBranch();
    $foreign = $otherTenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Not ours', 'price_cents' => 100, 'vat_category' => VatCategory::Standard]));

    sync([saleOf([[$foreign, 1]])])
        ->assertJsonPath('results.0.status', 'rejected')
        ->assertJsonPath('results.0.reason', 'unknown_items')
        ->assertJsonPath('results.0.errors.item_ids.0', $foreign->id);
});

it('rejects unknown fields, including a tenant in the body', function () {
    $sale = saleOf([[$this->nyama, 1]], overrides: ['tenant_id' => 'someone-else', 'branch_id' => (string) Str::uuid()]);
    $sale['lines'][0]['discount_cents'] = 500;

    sync([$sale])
        ->assertJsonPath('results.0.reason', 'invalid')
        ->assertJsonStructure(['results' => [['errors' => ['tenant_id', 'branch_id', 'lines.0.discount_cents']]]]);

    expect($this->tenant->run(fn () => Order::count()))->toBe(0);
});

it('voids a completed order on resend, but never un-voids it', function () {
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);

    sync([[...$sale, 'status' => 'voided']])->assertJsonPath('results.0.status', 'updated');
    sync([[...$sale, 'status' => 'completed']])->assertJsonPath('results.0.status', 'duplicate');

    expect($this->tenant->run(fn () => Order::findOrFail($sale['uuid'])->status->value))->toBe('voided')
        ->and(DomainEvent::query()->where('aggregate_id', $sale['uuid'])->pluck('event_type')->all())->toBe(['order.recorded', 'order.voided']);
    Event::assertDispatchedTimes(OrderVoided::class, 1);
});

it('rejects a second order with the same sequence number from the same device', function () {
    sync([saleOf([[$this->nyama, 1]], sequence: 7)]);

    sync([saleOf([[$this->water, 1]], sequence: 7)])
        ->assertJsonPath('results.0.status', 'rejected')
        ->assertJsonPath('results.0.reason', 'sequence_conflict');
});

it('keeps each tenant’s orders in its own schema', function () {
    [$otherTenant] = tenantWithBranch();

    sync([saleOf([[$this->nyama, 1]])])->assertJsonPath('results.0.status', 'accepted');

    expect($otherTenant->run(fn () => Order::count()))->toBe(0)
        ->and($this->tenant->run(fn () => Order::count()))->toBe(1);
});

it('commits nothing when the outbox write fails', function () {
    $sale = OrderData::from(saleOf([[$this->nyama, 1]]));
    // tenant_id is varchar(255): an over-long value makes the outbox insert fail mid-transaction.
    $context = new DeviceContext(str_repeat('x', 300), $this->branch->id, $this->enrolment->device->id);

    expect(fn () => $this->tenant->run(fn () => app(RecordOrder::class)->handle($sale, $context)))
        ->toThrow(QueryException::class);

    expect($this->tenant->run(fn () => Order::count()))->toBe(0)
        ->and(DomainEvent::count())->toBe(0);
    Event::assertNotDispatched(OrderRecorded::class);
});

it('keeps the outbox append-only, even for raw SQL', function () {
    sync([saleOf([[$this->nyama, 1]])]);
    $event = DomainEvent::query()->sole();

    expect(fn () => $event->update(['event_type' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => DB::table('domain_events')->update(['event_type' => 'tampered']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('domain_events')->delete())->toThrow(QueryException::class);
});

it('limits batches to 50 orders and bodies to 64 KB', function () {
    $sale = saleOf([[$this->nyama, 1]]);

    sync(array_fill(0, 51, $sale))->assertUnprocessable()->assertJsonValidationErrors('orders');

    $huge = saleOf([[$this->nyama, 1]], overrides: ['lines' => array_fill(0, 1000, $sale['lines'][0])]); // ~180 KB
    sync([$huge])->assertStatus(413);
});

it('requires the orders:sync ability', function () {
    $token = $this->enrolment->device->createToken('read-only', ['catalogue:read'], now()->addHour())->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}", 'X-Device-ID' => $this->enrolment->device->id])
        ->postJson('/api/v1/orders/sync', ['orders' => [saleOf([[$this->nyama, 1]])]])
        ->assertForbidden();
});
