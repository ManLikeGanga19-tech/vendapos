<?php

declare(strict_types=1);

use App\CashUp\CashUpRefused;
use App\CashUp\CloseShift;
use App\CashUp\Count;
use App\CashUp\Drawer;
use App\CashUp\OpenShift;
use App\CashUp\TakeCashOut;
use App\Jobs\PushOrdersJob;
use App\Livewire\StatusBar;
use App\Models\CashMovement;
use App\Models\Item;
use App\Models\Order;
use App\Models\Shift;
use App\Models\StaffMember;
use App\Models\SyncState;
use App\Sales\RecordSale;
use App\Sales\SaleRefused;
use App\Sales\VoidRefused;
use App\Sales\VoidSale;
use App\Security\SecretBox;
use App\Sync\SyncShifts;
use App\Sync\SyncStaff;
use App\Sync\SyncTill;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Enums\CashMovementKind;
use VendaPOS\Core\Enums\KesDenomination;
use VendaPOS\Core\Enums\VoidReason;

const CASHUP_SODA = '0199b1f2-0000-7000-8000-00000000c501';

beforeEach(function () {
    Queue::fake();
    $this->travelTo('2026-11-22T06:00:00Z'); // 09:00 in Nairobi
    pairTill();
    Item::query()->insert(['id' => CASHUP_SODA, 'category_id' => null, 'type' => 'product', 'name' => 'Soda', 'sku' => null, 'barcode' => null, 'price_cents' => 8_000, 'vat_category' => 'standard', 'is_active' => true]);

    $member = fn (string $id, string $name, string $role, array $permissions) => StaffMember::query()->create(SyncStaff::row(
        ['id' => $id, 'name' => $name, 'role' => $role, 'permissions' => $permissions, 'pin_hash' => '$2y$04$hash', 'pin_length' => 4],
        app(SecretBox::class),
    ));
    $this->cashier = $member('0199b1f2-0000-7000-8000-00000000c601', 'Achieng', 'cashier', ['orders.take', 'payments.take']);
    $this->manager = $member('0199b1f2-0000-7000-8000-00000000c602', 'Baraka', 'manager', ['orders.take', 'payments.take', 'orders.void', 'shifts.close']);
    $this->waiter = $member('0199b1f2-0000-7000-8000-00000000c603', 'Otieno', 'waiter', ['orders.take']);
});

/** @param array<int, int> $float */
function cashUpOpen(array $float = [1000 => 5]): Shift
{
    return app(OpenShift::class)->handle(test()->cashier, Count::of($float));
}

/** $sodas at KES 80 each, paid exactly in cash, or by a typed M-Pesa code. */
function cashUpSell(int $sodas, bool $mpesa = false): Order
{
    $amount = 8_000 * $sodas;
    $tender = $mpesa
        ? ['method' => 'mpesa_code', 'amount_cents' => $amount, 'mpesa_reference' => strtoupper(Str::random(10))]
        : ['method' => 'cash', 'amount_cents' => $amount, 'tendered_cents' => $amount, 'change_cents' => 0];

    return app(RecordSale::class)->handle(test()->cashier, [['item_id' => CASHUP_SODA, 'quantity' => $sodas]], [TenderData::from($tender)]);
}

/** @param array<int, int> $counts */
function cashUpClose(array $counts, ?StaffMember $approver = null, ?string $note = null): Shift
{
    return app(CloseShift::class)->handle(test()->cashier, Count::of($counts), $approver, $note);
}

function cashUpOut(CashMovementKind $kind, int $cents, ?string $reason = null, ?StaffMember $approver = null): CashMovement
{
    return app(TakeCashOut::class)->handle(test()->cashier, $approver ?? test()->manager, $kind, $cents, $reason);
}

/** Answers every shift or sale sent as accepted, unless $answer says otherwise. */
function cashUpServer(?Closure $answer = null): void
{
    $answer ??= fn (array $sent) => 'accepted';

    Http::fake([
        TEST_API_URL.'/api/v1/shifts/sync' => fn (Request $request) => Http::response(['results' => array_map(
            fn (array $shift) => ['uuid' => $shift['uuid'], 'status' => $answer($shift), 'reason' => $answer($shift) === 'rejected' ? 'number_conflict' : null],
            $request['shifts'],
        )]),
        TEST_API_URL.'/api/v1/orders/sync' => fn (Request $request) => Http::response(['results' => array_map(
            fn (array $order) => ['uuid' => $order['uuid'], 'status' => 'accepted'],
            $request['orders'],
        )]),
    ]);
}

/** @return list<array<string, mixed>> every shift sent, in order */
function shiftsSent(): array
{
    return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/shifts/sync'))
        ->flatMap(fn (array $pair) => $pair[0]['shifts'])->values()->all();
}

describe('opening', function () {
    it('refuses to sell until a shift is open, then names the shift on every sale', function () {
        expect(fn () => cashUpSell(1))->toThrow(SaleRefused::class, 'no_shift')
            ->and(Order::query()->count())->toBe(0);

        $shift = cashUpOpen();
        $order = cashUpSell(1);

        expect($order->shift_id)->toBe($shift->id)
            ->and($order->toSyncPayload()['shift_id'])->toBe($shift->id);
    });

    it('opens one shift at a time, and only for someone who takes payments', function () {
        expect(fn () => app(OpenShift::class)->handle($this->waiter, Count::of([1000 => 1])))->toThrow(CashUpRefused::class, 'not_allowed');

        cashUpOpen();

        expect(fn () => cashUpOpen())->toThrow(CashUpRefused::class, 'already_open')
            ->and(Shift::query()->count())->toBe(1);
        Queue::assertPushed(PushOrdersJob::class);
    });

    it('numbers shifts on from the last one the server has, so a reinstalled till never reuses one', function () {
        SyncState::write(Drawer::LAST_NUMBER, '41');

        expect(cashUpOpen()->number)->toBe(42);

        cashUpClose([1000 => 5]);

        expect(cashUpOpen()->number)->toBe(43);
    });

    it('takes counts as typed: blank is none, anything but a sensible whole number is refused', function () {
        expect(Count::of(['1000' => '3', '50' => '', '1' => '0'])->counts)->toBe([1000 => 3])
            ->and(Count::of([])->total()->cents)->toBe(0);

        foreach (['-1', '1.5', 'abc', '10001'] as $typed) {
            expect(fn () => Count::of(['500' => $typed]))->toThrow(CashUpRefused::class, 'count');
        }
    });
});

describe('cash out', function () {
    it('takes a pay-out or drop approved by someone who may close shifts, never more than the drawer holds', function () {
        cashUpOpen([1000 => 5]);
        cashUpSell(1); // KES 5,080 in the drawer

        expect(fn () => cashUpOut(CashMovementKind::Drop, 10_000, approver: $this->cashier))->toThrow(CashUpRefused::class, 'approver_not_allowed')
            ->and(fn () => cashUpOut(CashMovementKind::PayOut, 10_000, '  '))->toThrow(CashUpRefused::class, 'reason_needed')
            ->and(fn () => cashUpOut(CashMovementKind::Drop, 508_100))->toThrow(CashUpRefused::class, 'more_than_drawer')
            ->and(fn () => cashUpOut(CashMovementKind::VoidRefund, 1_000))->toThrow(CashUpRefused::class, 'not_allowed')
            ->and(fn () => cashUpOut(CashMovementKind::Drop, 50))->toThrow(CashUpRefused::class, 'amount');

        $payOut = cashUpOut(CashMovementKind::PayOut, 5_000, ' Charcoal ');
        cashUpOut(CashMovementKind::Drop, 300_000, 'ignored for a drop');

        expect($payOut->only(['kind', 'amount_cents', 'reason', 'staff_id', 'approved_by', 'synced']))->toBe([
            'kind' => CashMovementKind::PayOut, 'amount_cents' => 5_000, 'reason' => 'Charcoal', 'staff_id' => $this->cashier->id, 'approved_by' => $this->manager->id, 'synced' => false,
        ])
            ->and(CashMovement::query()->where('kind', CashMovementKind::Drop)->value('reason'))->toBeNull()
            ->and(app(Drawer::class)->cash(app(Drawer::class)->current())->expected()->cents)->toBe(203_000);
    });

    it('takes no cash out with no shift open', function () {
        expect(fn () => cashUpOut(CashMovementKind::Drop, 10_000))->toThrow(CashUpRefused::class, 'no_shift');
    });
});

describe('closing', function () {
    it('closes a count within the allowance without a manager, and selling stops until the next shift', function () {
        $shift = cashUpOpen([1000 => 5]);
        cashUpSell(2);              // + KES 160 cash
        cashUpSell(1, mpesa: true); // not in the drawer
        cashUpOut(CashMovementKind::PayOut, 5_000, 'Charcoal');

        // Expected KES 5,110; counted KES 5,050: KES 60 short, inside the KES 100 allowance.
        $check = app(CloseShift::class)->check($shift, Count::of([1000 => 5, 50 => 1]));
        expect([$check->expected->cents, $check->variance->cents, $check->needsApproval])->toBe([511_000, -6_000, false]);

        $closed = cashUpClose([1000 => 5, 50 => 1]);

        expect($closed->only(['closed_by', 'expected_cents', 'approved_by', 'note', 'close_synced']))->toBe([
            'closed_by' => $this->cashier->id, 'expected_cents' => 511_000, 'approved_by' => null, 'note' => null, 'close_synced' => false,
        ])
            ->and($closed->closingCount()?->total()->cents)->toBe(505_000)
            ->and(app(Drawer::class)->isOpen())->toBeFalse()
            ->and(fn () => cashUpSell(1))->toThrow(SaleRefused::class, 'no_shift');
    });

    it('needs a manager and a note to close a count off by more than the allowance', function () {
        cashUpOpen([1000 => 5]);
        $short = [1000 => 4]; // KES 1,000 short

        expect(fn () => cashUpClose($short))->toThrow(CashUpRefused::class, 'approval_needed')
            ->and(fn () => cashUpClose($short, $this->cashier, 'Recounted'))->toThrow(CashUpRefused::class, 'approver_not_allowed')
            ->and(fn () => cashUpClose($short, $this->manager, ' '))->toThrow(CashUpRefused::class, 'note_needed')
            ->and(app(Drawer::class)->isOpen())->toBeTrue();

        $closed = cashUpClose($short, $this->manager, 'KES 1,000 note given as change for a 500');

        expect($closed->only(['approved_by', 'note']))->toBe(['approved_by' => $this->manager->id, 'note' => 'KES 1,000 note given as change for a 500']);
    });

    it('follows the allowance the server sends', function () {
        SyncState::write(Drawer::ALLOWANCE, '150000'); // KES 1,500
        $shift = cashUpOpen([1000 => 5]);

        expect(app(CloseShift::class)->check($shift, Count::of([1000 => 4]))->needsApproval)->toBeFalse()
            ->and(cashUpClose([1000 => 4])->approved_by)->toBeNull();
    });
});

describe('voids', function () {
    beforeEach(function () {
        // Offline: today's sales come from this till alone.
        Http::fake(fn () => throw new ConnectionException('offline'));
    });

    it('takes the cash given back out of the open shift as a void refund', function () {
        $shift = cashUpOpen([1000 => 5]);
        $sale = cashUpSell(2);

        app(VoidSale::class)->handle($this->cashier, $this->manager, $sale->id, VoidReason::WrongItem, null);

        $refund = CashMovement::query()->sole();
        expect($refund->only(['shift_id', 'kind', 'amount_cents', 'staff_id', 'approved_by', 'order_id']))->toBe([
            'shift_id' => $shift->id, 'kind' => CashMovementKind::VoidRefund, 'amount_cents' => 16_000,
            // The void carries whose PIN approved it; the server checks movement approvers for shifts.close.
            'staff_id' => $this->cashier->id, 'approved_by' => null, 'order_id' => $sale->id,
        ])
            // The sale's cash came in and went back out: the drawer is back to its float.
            ->and(app(Drawer::class)->cash($shift)->expected()->cents)->toBe(500_000);
    });

    it('refuses a cash void with no shift open, but an M-Pesa void needs no drawer', function () {
        cashUpOpen([1000 => 5]);
        $cash = cashUpSell(1);
        $mpesa = cashUpSell(1, mpesa: true);
        cashUpClose([1000 => 5, 50 => 1, 20 => 1, 10 => 1]);

        expect(fn () => app(VoidSale::class)->handle($this->cashier, $this->manager, $cash->id, VoidReason::WrongItem, null))
            ->toThrow(VoidRefused::class)
            ->and($cash->fresh()?->status)->toBe('completed');

        app(VoidSale::class)->handle($this->cashier, $this->manager, $mpesa->id, VoidReason::WrongItem, null);

        expect($mpesa->fresh()?->status)->toBe('voided')
            ->and(CashMovement::query()->count())->toBe(0);
    });
});

describe('sync', function () {
    it('sends the shift before the sales that name it, with every denomination, its cash out and the close', function () {
        cashUpServer();
        $shift = cashUpOpen([1000 => 5]);
        cashUpSell(1);
        $payOut = cashUpOut(CashMovementKind::PayOut, 5_000, 'Charcoal');
        cashUpClose([1000 => 5, 20 => 1, 10 => 1]);

        app()->call([app(PushOrdersJob::class), 'handle']);

        $paths = Http::recorded()->map(fn (array $pair) => parse_url($pair[0]->url(), PHP_URL_PATH))->all();
        expect($paths)->toBe(['/api/v1/shifts/sync', '/api/v1/orders/sync']);

        $sent = shiftsSent()[0];
        expect($sent['uuid'])->toBe($shift->id)
            ->and(array_keys($sent['opening_float']['counts']))->toBe(KesDenomination::shillings())
            ->and($sent['opening_float']['counts'][1000])->toBe(5)
            ->and($sent['movements'])->toBe([[
                'uuid' => $payOut->id, 'kind' => 'pay_out', 'amount_cents' => 5_000, 'moved_at' => $payOut->moved_at->toIso8601String(),
                'staff_id' => $this->cashier->id, 'approved_by' => $this->manager->id, 'reason' => 'Charcoal',
            ]])
            ->and($sent['closing_count']['counts'][20])->toBe(1)
            ->and($sent['expected_cents'])->toBe(503_000)
            ->and($sent)->not->toHaveKeys(['approved_by', 'note']);

        expect($shift->fresh()?->only(['sync_status', 'close_synced']))->toBe(['sync_status' => Shift::SYNCED, 'close_synced' => true])
            ->and(CashMovement::query()->where('synced', false)->count())->toBe(0);

        // Nothing new: nothing sent.
        app(SyncShifts::class)->handle();
        expect(shiftsSent())->toHaveCount(1);
    });

    it('sends a busy shift’s cash out over several requests under the size limit, closing only with the last', function () {
        cashUpServer();
        $shift = cashUpOpen([1000 => 1000]);
        Shift::query()->whereKey($shift->id)->update(['sync_status' => Shift::SYNCED]);

        $ids = [];
        for ($i = 0; $i < 450; $i++) {
            $ids[] = CashMovement::query()->create([
                'id' => (string) Str::uuid7(), 'shift_id' => $shift->id, 'kind' => CashMovementKind::PayOut, 'amount_cents' => 100,
                'moved_at' => now()->addSeconds($i), 'staff_id' => $this->cashier->id, 'approved_by' => $this->manager->id,
                'reason' => str_repeat('Mandazi na chai kwa wafanyakazi ', 6),
            ])->id;
        }
        cashUpClose([1000 => 999, 500 => 1, 50 => 1]); // exactly the KES 999,550 left

        app(SyncShifts::class)->handle();

        $requests = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/shifts/sync'));
        $sent = shiftsSent();

        expect($requests->count())->toBeGreaterThan(1)
            ->and($requests->every(fn (array $pair) => strlen($pair[0]->body()) <= SyncShifts::MAX_BYTES))->toBeTrue()
            ->and(collect($sent)->flatMap(fn (array $s) => array_column($s['movements'], 'uuid'))->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all())
            ->and(array_map(fn (array $s) => array_key_exists('closed_at', $s), $sent))->toBe([...array_fill(0, count($sent) - 1, false), true])
            ->and($shift->fresh()?->close_synced)->toBeTrue();
    });

    it('keeps a refused shift for a person to look at, and sends it no more', function () {
        cashUpServer(fn () => 'rejected');
        $shift = cashUpOpen();

        app(SyncShifts::class)->handle();
        app(SyncShifts::class)->handle();

        expect($shift->fresh()?->only(['sync_status', 'sync_reason']))->toBe(['sync_status' => Shift::REJECTED, 'sync_reason' => 'number_conflict'])
            ->and(shiftsSent())->toHaveCount(1);

        // Said on every screen, so it is not only in a log nobody reads.
        Livewire::test(StatusBar::class)->assertSee(trans_choice('terminal.status.shifts_refused', 1, ['count' => 1]));
    });

    it('keeps the allowance and the last shift number the server sends', function () {
        Http::fake([
            TEST_API_URL.'/api/v1/device' => Http::response([
                'device' => ['id' => TEST_DEVICE_ID, 'name' => 'Till 1', 'till_code' => 'T1'],
                'branch' => ['id' => '0199b1f2-0000-7000-8000-000000000001', 'name' => 'Nyali', 'business_type' => 'shop', 'terminal_mode' => 'quick_sale'], // not table service: no tickets
                'token_expires_at' => now()->addHours(12)->toIso8601String(),
                'account_status' => 'active',
                'cash_up' => ['variance_allowed_cents' => 25_000, 'last_shift_number' => 17],
            ]),
            TEST_API_URL.'/api/v1/staff' => Http::response(['synced_at' => now()->toIso8601String(), 'staff' => []]),
            TEST_API_URL.'/api/v1/catalogue*' => Http::response(['synced_at' => now()->toIso8601String(), 'full' => true, 'categories' => [], 'items' => []]),
            TEST_API_URL.'/api/v1/floor' => Http::response(['areas' => []]),
        ]);

        expect(app(SyncTill::class)->handle())->toBeTrue()
            ->and(app(Drawer::class)->allowance()->cents)->toBe(25_000)
            ->and(app(Drawer::class)->nextNumber())->toBe(18);
    });
});
