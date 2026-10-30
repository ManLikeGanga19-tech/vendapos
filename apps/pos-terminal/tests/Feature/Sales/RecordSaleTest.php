<?php

declare(strict_types=1);

use App\Jobs\PushOrdersJob;
use App\Models\Item;
use App\Models\Order;
use App\Models\ReceiptReprint;
use App\Models\StaffMember;
use App\Sales\RecordSale;
use App\Sales\SaleRefused;
use App\Security\SecretBox;
use App\Sync\SyncOrders;
use App\Sync\SyncReprints;
use App\Sync\SyncStaff;
use App\Sync\SyncTill;
use App\Till\Till;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Tax\OrderTotals;

const NYAMA = '0199b1f2-0000-7000-8000-00000000a001';
const SODA = '0199b1f2-0000-7000-8000-00000000a002';

beforeEach(function () {
    Queue::fake();
    pairTill();

    Item::query()->insert([
        ['id' => NYAMA, 'category_id' => null, 'type' => 'product', 'name' => 'Nyama choma', 'sku' => null, 'barcode' => null, 'price_cents' => 185_000, 'vat_category' => 'standard', 'is_active' => true],
        ['id' => SODA, 'category_id' => null, 'type' => 'product', 'name' => 'Soda 500 ml', 'sku' => null, 'barcode' => '6161101560012', 'price_cents' => 7_000, 'vat_category' => 'zero_rated', 'is_active' => true],
    ]);

    $this->cashier = saleStaff('0199b1f2-0000-7000-8000-00000000b001', 'Achieng', ['orders.take', 'payments.take']);
});

/** @param list<string> $permissions */
function saleStaff(string $id, string $name, array $permissions): StaffMember
{
    return StaffMember::query()->create(SyncStaff::row(
        ['id' => $id, 'name' => $name, 'role' => 'cashier', 'permissions' => $permissions, 'pin_hash' => '$2y$04$hash', 'pin_length' => 4],
        app(SecretBox::class),
    ));
}

function cash(int $amount, ?int $tendered = null): TenderData
{
    $tendered ??= $amount;

    return TenderData::from(['method' => 'cash', 'amount_cents' => $amount, 'tendered_cents' => $tendered, 'change_cents' => $tendered - $amount]);
}

function sell(array $cart, array $tenders): Order
{
    return app(RecordSale::class)->handle(test()->cashier, $cart, $tenders);
}

it('fixes each sale’s receipt number at the sale, from the till code it had then', function () {
    $first = sell([['item_id' => SODA, 'quantity' => 1]], [cash(7_000)]);
    app(Till::class)->identity()?->update(['till_code' => 'T9']);
    $second = sell([['item_id' => SODA, 'quantity' => 1]], [cash(7_000)]);

    expect($first->fresh()?->receipt_number)->toBe('T1-000001')
        ->and($second->receipt_number)->toBe('T9-000002')
        // The number is not sent: the server works out the same one from this till.
        ->and($second->toSyncPayload())->not->toHaveKey('receipt_number');
});

it('records a cash sale locally with core’s totals, numbered and pending', function () {
    $order = sell([['item_id' => NYAMA, 'quantity' => 1], ['item_id' => SODA, 'quantity' => 2]], [cash(199_000, 200_000)]);

    $expected = OrderTotals::fromLines([
        OrderLineData::from(['item_id' => NYAMA, 'name' => 'Nyama choma', 'quantity' => 1, 'unit_price_cents' => 185_000, 'vat_category' => 'standard', 'line_total_cents' => 185_000]),
        OrderLineData::from(['item_id' => SODA, 'name' => 'Soda 500 ml', 'quantity' => 2, 'unit_price_cents' => 7_000, 'vat_category' => 'zero_rated', 'line_total_cents' => 14_000]),
    ]);

    expect($order->fresh())
        ->sequence_number->toBe(1)
        ->staff_id->toBe($this->cashier->id)
        ->status->toBe('completed')
        ->sync_status->toBe(Order::PENDING)
        ->total_cents->toBe(199_000)
        ->vat_cents->toBe($expected->vat->cents)
        ->subtotal_cents->toBe($expected->subtotal->cents)
        ->and($order->lines()->pluck('name')->all())->toBe(['Nyama choma', 'Soda 500 ml'])
        ->and($order->tenders()->sole())->change_cents->toBe(1_000);

    Queue::assertPushed(PushOrdersJob::class);
});

it('numbers sales one after another', function () {
    sell([['item_id' => SODA, 'quantity' => 1]], [cash(7_000)]);
    $second = sell([['item_id' => SODA, 'quantity' => 1]], [cash(7_000)]);

    expect($second->sequence_number)->toBe(2);
});

it('prices from the till’s menu and refuses items it does not sell', function (array $cart) {
    expect(fn () => sell($cart, [cash(7_000)]))->toThrow(SaleRefused::class, 'unknown_item');
    expect(Order::query()->count())->toBe(0);
})->with([
    'not on the menu' => [[['item_id' => (string) Str::uuid(), 'quantity' => 1]]],
    'switched off' => [function () {
        Item::query()->whereKey(SODA)->update(['is_active' => false]);

        return [['item_id' => SODA, 'quantity' => 1]];
    }],
    'no quantity' => [[['item_id' => SODA, 'quantity' => 0]]],
]);

it('refuses tenders that do not pay the sale exactly, with core’s reasons', function () {
    expect(fn () => sell([['item_id' => NYAMA, 'quantity' => 1]], [cash(100_000)]))
        ->toThrow(fn (SaleRefused $e) => expect($e->reason)->toBe('tenders')->and($e->errors)->toHaveKey('tenders'));

    // M-Pesa takes whole shillings only.
    expect(fn () => sell([['item_id' => NYAMA, 'quantity' => 1]], [TenderData::from(['method' => 'mpesa_push', 'amount_cents' => 185_000 - 50, 'payment_uuid' => (string) Str::uuid()]), cash(50)]))
        ->toThrow(SaleRefused::class);
});

it('refuses an empty sale, and staff who may not take payments', function () {
    expect(fn () => sell([], [cash(7_000)]))->toThrow(SaleRefused::class, 'empty');

    $waiter = saleStaff('0199b1f2-0000-7000-8000-00000000b002', 'Baraka', ['orders.take']);

    expect(fn () => app(RecordSale::class)->handle($waiter, [['item_id' => SODA, 'quantity' => 1]], [cash(7_000)]))
        ->toThrow(SaleRefused::class, 'not_allowed');
});

describe('sync', function () {
    function syncAnswer(callable $statusFor): Closure
    {
        return fn (Request $request) => Http::response(['results' => array_map(
            fn (array $order) => array_filter(['uuid' => $order['uuid'], ...$statusFor($order)]),
            $request['orders'],
        )]);
    }

    it('sends pending sales in the server’s format and marks them synced', function () {
        $order = sell([['item_id' => SODA, 'quantity' => 2]], [cash(14_000, 20_000)]);
        Http::fake([TEST_API_URL.'/api/v1/orders/sync' => syncAnswer(fn () => ['status' => 'accepted'])]);

        expect(app(SyncOrders::class)->handle())->toBe(1)
            ->and($order->fresh()?->sync_status)->toBe(Order::SYNCED);

        Http::assertSent(function (Request $request) use ($order) {
            $sent = $request['orders'][0];

            return $request->hasHeader('X-Device-ID', TEST_DEVICE_ID)
                && $sent['uuid'] === $order->id
                && $sent['staff_id'] === $this->cashier->id
                && $sent['sequence_number'] === 1
                && $sent['lines'][0] === ['item_id' => SODA, 'name' => 'Soda 500 ml', 'quantity' => 2, 'unit_price_cents' => 7_000, 'vat_category' => 'zero_rated', 'line_total_cents' => 14_000]
                && $sent['tenders'] === [['method' => 'cash', 'amount_cents' => 14_000, 'tendered_cents' => 20_000, 'change_cents' => 6_000]]
                && $sent['total_cents'] === 14_000;
        });
    });

    it('sends at most 50 at a time, oldest first, until none are left', function () {
        foreach (range(1, 120) as $n) {
            sell([['item_id' => SODA, 'quantity' => 1]], [cash(7_000)]);
        }
        Http::fake([TEST_API_URL.'/api/v1/orders/sync' => syncAnswer(fn () => ['status' => 'accepted'])]);

        app(SyncOrders::class)->handle();

        $batches = Http::recorded()->map(fn (array $pair) => array_column($pair[0]['orders'], 'sequence_number'))->values();

        expect($batches)->toHaveCount(3)
            ->and(array_map('count', $batches->all()))->toBe([50, 50, 20])
            ->and($batches[0][0])->toBe(1)
            ->and(Order::query()->where('sync_status', Order::SYNCED)->count())->toBe(120);
    });

    it('treats a duplicate as synced, and keeps a rejected sale with the server’s reason', function () {
        $first = sell([['item_id' => SODA, 'quantity' => 1]], [cash(7_000)]);
        $second = sell([['item_id' => SODA, 'quantity' => 1]], [cash(7_000)]);
        Http::fake([TEST_API_URL.'/api/v1/orders/sync' => syncAnswer(fn (array $order) => $order['uuid'] === $first->id
            ? ['status' => 'duplicate']
            : ['status' => 'rejected', 'reason' => 'unknown_staff', 'errors' => ['staff_id' => 'No one at this business has this ID.']])]);

        app(SyncOrders::class)->handle();
        app(SyncOrders::class)->handle();

        expect($first->fresh()?->sync_status)->toBe(Order::SYNCED)
            ->and($second->fresh())->sync_status->toBe(Order::REJECTED)->sync_reason->toBe('unknown_staff')
            ->and($second->fresh()?->sync_errors)->toBe(['staff_id' => 'No one at this business has this ID.']);
        // The rejected sale is not resent by itself.
        Http::assertSentCount(1);
    });

    it('sends reprints once the server has their sale, and keeps a refused one with its reason', function () {
        $synced = sell([['item_id' => SODA, 'quantity' => 1]], [cash(7_000)]);
        $waiting = sell([['item_id' => SODA, 'quantity' => 1]], [cash(7_000)]);
        $synced->update(['sync_status' => Order::SYNCED]);

        $reprint = fn (Order $order) => ReceiptReprint::query()->create(['id' => (string) Str::uuid7(), 'order_id' => $order->id, 'staff_id' => $this->cashier->id, 'reprinted_at' => now()]);
        $sent = $reprint($synced);
        $refused = $reprint($synced);
        $held = $reprint($waiting);

        Http::fake([TEST_API_URL.'/api/v1/receipts/reprints/sync' => fn (Request $request) => Http::response(['results' => array_map(
            fn (array $r) => $r['uuid'] === $refused->id ? ['uuid' => $r['uuid'], 'status' => 'rejected', 'reason' => 'unknown_staff'] : ['uuid' => $r['uuid'], 'status' => 'accepted'],
            $request['reprints'],
        )])]);

        app(SyncReprints::class)->handle();

        expect($sent->fresh()?->sync_status)->toBe('synced')
            ->and($refused->fresh())->sync_status->toBe('rejected')->sync_reason->toBe('unknown_staff')
            ->and($held->fresh()?->sync_status)->toBe('pending');
        Http::assertSent(fn (Request $request) => $request['reprints'][0] === [
            'uuid' => $sent->id,
            'order_uuid' => $synced->id,
            'staff_id' => $this->cashier->id,
            'reprinted_at' => $sent->reprinted_at->toIso8601String(),
        ] && count($request['reprints']) === 2);
    });

    it('keeps every sale pending while offline, and sends them first in the next round', function () {
        $order = sell([['item_id' => SODA, 'quantity' => 1]], [cash(7_000)]);
        Http::fake(fn () => throw new ConnectionException('Network is unreachable'));

        app(SyncTill::class)->handle();
        app(PushOrdersJob::class)->handle(app(SyncOrders::class), app(Till::class));

        expect($order->fresh()?->sync_status)->toBe(Order::PENDING);
    });
});
