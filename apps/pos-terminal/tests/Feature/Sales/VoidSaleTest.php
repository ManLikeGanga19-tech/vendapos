<?php

declare(strict_types=1);

use App\Jobs\PrintVoidSlipJob;
use App\Jobs\PushOrdersJob;
use App\Livewire\SalesToday;
use App\Models\Item;
use App\Models\Order;
use App\Models\StaffMember;
use App\Sales\RecordSale;
use App\Sales\VoidRefused;
use App\Sales\VoidSale;
use App\Security\SecretBox;
use App\Staff\TillSession;
use App\Sync\SyncStaff;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Enums\VoidReason;

const VOID_CHAI = '0199b1f2-0000-7000-8000-00000000f001';
const OTHER_TILL_SALE = '0199b1f2-0000-7000-8000-00000000f0aa';
const MANAGER_PIN = '258046';

beforeEach(function () {
    Queue::fake();
    $this->travelTo('2026-10-26T11:05:00Z'); // 14:05 in Nairobi
    pairTill();
    openTestShift(); // selling needs an open drawer shift (cash-up)
    Item::query()->insert(['id' => VOID_CHAI, 'category_id' => null, 'type' => 'product', 'name' => 'Chai', 'sku' => null, 'barcode' => null, 'price_cents' => 5_000, 'vat_category' => 'standard', 'is_active' => true]);

    $member = fn (string $id, string $name, string $role, array $permissions, string $pinHash, int $pinLength) => StaffMember::query()->create(SyncStaff::row(
        ['id' => $id, 'name' => $name, 'role' => $role, 'permissions' => $permissions, 'pin_hash' => $pinHash, 'pin_length' => $pinLength],
        app(SecretBox::class),
    ));
    $this->cashier = $member('0199b1f2-0000-7000-8000-00000000d101', 'Wanjiku', 'cashier', ['orders.take', 'payments.take'], '$2y$04$hash', 4);
    $this->manager = $member('0199b1f2-0000-7000-8000-00000000d102', 'Baraka', 'manager', ['orders.take', 'payments.take', 'orders.void'], password_hash(MANAGER_PIN, PASSWORD_BCRYPT, ['cost' => 4]), 6);
    $this->waiter = $member('0199b1f2-0000-7000-8000-00000000d103', 'Otieno', 'waiter', ['orders.take'], '$2y$04$hash', 4);
    app(TillSession::class)->signIn($this->cashier);
});

/** A sale of $cups chai on this till, paid in exact cash. */
function chaiSale(int $cups = 2): Order
{
    return app(RecordSale::class)->handle(test()->cashier, [['item_id' => VOID_CHAI, 'quantity' => $cups]], [
        TenderData::from(['method' => 'cash', 'amount_cents' => 5_000 * $cups, 'tendered_cents' => 5_000 * $cups, 'change_cents' => 0]),
    ]);
}

/** One entry of GET /api/v1/orders/today, as the server sends it. */
function serverSale(array $overrides = []): array
{
    return array_merge([
        'id' => OTHER_TILL_SALE, 'device_id' => '01a1644c-0000-7000-8000-0000000000t2', 'receipt_number' => 'T2-000009',
        'staff_id' => '0199b1f2-0000-7000-8000-00000000d101', 'ticket_id' => null, 'status' => 'completed',
        'placed_at' => '2026-10-26T13:30:00+03:00', 'subtotal_cents' => 8_621, 'vat_cents' => 1_379, 'total_cents' => 10_000,
        'lines' => [['item_id' => VOID_CHAI, 'name' => 'Chai', 'quantity' => 2, 'unit_price_cents' => 5_000, 'vat_category' => 'standard', 'line_total_cents' => 10_000]],
        'tenders' => [['method' => 'mpesa_code', 'amount_cents' => 10_000, 'mpesa_reference' => 'TJL7QW2E9R']],
        'void' => null,
    ], $overrides);
}

/**
 * The server: today's list is $sales; a void answers $voidStatus. Offline when $sales is null.
 *
 * @param  list<array<string, mixed>>|null  $sales
 */
function fakeSalesServer(?array $sales, int $voidStatus = 200): void
{
    Http::fake([
        TEST_API_URL.'/api/v1/orders/today' => $sales === null
            ? fn () => throw new ConnectionException('offline')
            : Http::response(['orders' => $sales]),
        TEST_API_URL.'/api/v1/orders/*/void' => fn (Request $request) => Http::response(['uuid' => OTHER_TILL_SALE, 'status' => 'updated'], $voidStatus),
    ]);
}

/** Through the screen: pick the sale, a reason, the manager, and the manager's PIN. */
function voidThroughScreen(string $saleId, string $reason = 'wrong_item', string $pin = MANAGER_PIN): Testable
{
    $screen = Livewire::test(SalesToday::class)
        ->call('select', $saleId)
        ->call('startVoid')
        ->assertSet('step', SalesToday::REASON)
        ->call('pickReason', $reason)
        ->assertSet('step', SalesToday::APPROVER)
        ->call('chooseApprover', test()->manager->id)
        ->assertSet('step', SalesToday::PIN);

    foreach (str_split($pin) as $digit) {
        $screen->call('press', $digit);
    }

    return $screen;
}

it('voids this till’s own cash sale offline, with the manager’s PIN, and sends it with the order', function () {
    fakeSalesServer(null);
    $sale = chaiSale();
    $sale->update(['sync_status' => Order::SYNCED]);

    voidThroughScreen($sale->id)
        ->assertSet('step', SalesToday::DONE)
        ->assertSee(__('terminal.sales.give_back'))
        ->assertSee('100.00');

    $sale->refresh();
    expect($sale->status)->toBe('voided')
        ->and($sale->voided_by)->toBe($this->cashier->id)
        ->and($sale->approved_by)->toBe($this->manager->id)
        ->and($sale->void_reason)->toBe('wrong_item')
        ->and($sale->sync_status)->toBe(Order::PENDING)
        ->and($sale->toSyncPayload())->toMatchArray(['status' => 'voided', 'void' => [
            'voided_at' => '2026-10-26T11:05:00+00:00', 'voided_by' => $this->cashier->id, 'approved_by' => $this->manager->id, 'reason' => 'wrong_item', 'note' => null,
        ]]);

    Queue::assertPushed(PushOrdersJob::class);
    Queue::assertPushed(PrintVoidSlipJob::class, fn (PrintVoidSlipJob $job) => $job->sale->cashCents === 10_000 && $job->sale->approvedBy === 'Baraka');
});

it('keeps the sale when the approver’s PIN is wrong', function () {
    fakeSalesServer(null);
    $sale = chaiSale();

    voidThroughScreen($sale->id, pin: '111111')
        ->assertSet('step', SalesToday::PIN)
        ->assertSee(trans_choice('terminal.sign_in.wrong', 4, ['count' => 4]));

    expect($sale->fresh()?->status)->toBe('completed');
    Queue::assertNotPushed(PrintVoidSlipJob::class);
});

it('asks what happened when the reason is "other"', function () {
    fakeSalesServer(null);
    $sale = chaiSale();

    Livewire::test(SalesToday::class)
        ->call('select', $sale->id)
        ->call('startVoid')
        ->call('pickReason', 'other')
        ->assertSet('step', SalesToday::REASON)
        ->call('confirmReason')
        ->assertHasErrors('note')
        ->set('note', 'Customer walked out')
        ->call('confirmReason')
        ->assertSet('step', SalesToday::APPROVER);
});

it('asks for an approver’s PIN even when a manager is the one signed in', function () {
    fakeSalesServer(null);
    $sale = chaiSale();
    app(TillSession::class)->signIn($this->manager);

    Livewire::test(SalesToday::class)
        ->call('select', $sale->id)
        ->call('startVoid')
        ->call('pickReason', 'duplicate')
        ->assertSet('step', SalesToday::APPROVER)
        ->assertSee('Baraka');

    expect($sale->fresh()?->status)->toBe('completed');
});

it('keeps waiters off the screen, and voids only with someone who may approve', function () {
    fakeSalesServer(null);
    $sale = chaiSale();
    $void = app(VoidSale::class);

    expect(fn () => $void->handle($this->waiter, $this->manager, $sale->id, VoidReason::WrongItem, null))->toThrow(VoidRefused::class, 'not_allowed')
        ->and(fn () => $void->handle($this->cashier, $this->cashier, $sale->id, VoidReason::WrongItem, null))->toThrow(VoidRefused::class, 'approver_not_allowed')
        ->and($sale->fresh()?->status)->toBe('completed');

    app(TillSession::class)->signIn($this->waiter);
    Livewire::test(SalesToday::class)->assertForbidden();
});

it('voids another till’s sale on the server while online, and leaves the M-Pesa refund to the owner', function () {
    fakeSalesServer([serverSale()]);

    voidThroughScreen(OTHER_TILL_SALE, 'changed_mind')
        ->assertSet('step', SalesToday::DONE)
        ->assertSee(__('terminal.sales.mpesa_back', ['amount' => 'KES 100.00']))
        ->assertDontSee(__('terminal.sales.give_back'));

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/orders/'.OTHER_TILL_SALE.'/void')
        && $request['voided_by'] === $this->cashier->id
        && $request['approved_by'] === $this->manager->id
        && $request['reason'] === 'changed_mind');
    expect(Order::query()->count())->toBe(0);
    Queue::assertPushed(PrintVoidSlipJob::class, fn (PrintVoidSlipJob $job) => $job->sale->mpesaCents === 10_000 && $job->sale->cashCents === 0);
});

it('says so when another till’s sale cannot be voided right now', function (int $status, string $reason) {
    fakeSalesServer([serverSale()], voidStatus: $status);

    voidThroughScreen(OTHER_TILL_SALE)
        ->assertSet('step', SalesToday::SALE)
        ->assertSee(__("terminal.sales.refused.{$reason}"));

    Queue::assertNotPushed(PrintVoidSlipJob::class);
})->with([
    'already voided by another till' => [409, 'already_voided'],
    'unknown on the server' => [404, 'not_found'],
    'refused staff' => [422, 'refused'],
]);

it('cannot void another till’s sale when the server goes away mid-void', function () {
    Http::fake([
        TEST_API_URL.'/api/v1/orders/today' => Http::response(['orders' => [serverSale()]]),
        TEST_API_URL.'/api/v1/orders/*/void' => fn () => throw new ConnectionException('offline'),
    ]);

    voidThroughScreen(OTHER_TILL_SALE)->assertSee(__('terminal.sales.refused.offline'));
});

it('lists today’s sales from every till, this till’s unsynced ones too, newest first', function () {
    $mine = chaiSale(1);
    $old = chaiSale(3);
    $old->update(['placed_at' => '2026-10-25T20:00:00Z']); // 23:00 yesterday in Nairobi
    fakeSalesServer([serverSale()]);

    Livewire::test(SalesToday::class)
        ->assertSee(['T1-000001', 'T2-000009', __('terminal.sales.all_tills')])
        ->assertDontSee('T1-000002')
        ->set('search', 'tjl7')
        ->assertSee('T2-000009')
        ->assertDontSee('T1-000001');

    // 14:05 here beats 13:30 on the other till.
    expect(array_map(fn ($sale) => $sale->id, app(App\Sales\SalesToday::class)->list()['sales']))->toBe([$mine->id, OTHER_TILL_SALE]);
});

it('shows this till’s sale as voided once another till has voided it', function () {
    $sale = chaiSale();
    fakeSalesServer([serverSale(['id' => $sale->id, 'receipt_number' => 'T1-000001', 'status' => 'voided', 'void' => ['reason' => 'duplicate']])]);

    Livewire::test(SalesToday::class)
        ->call('select', $sale->id)
        ->assertSee(__('terminal.sales.already_voided', ['reason' => __('terminal.void_reasons.duplicate')]))
        ->assertDontSee(__('terminal.sales.void'))
        ->call('startVoid')
        ->assertSet('step', SalesToday::SALE);
});
