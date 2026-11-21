<?php

declare(strict_types=1);

use App\Livewire\Sale;
use App\Models\Item;
use App\Models\Order;
use App\Models\StaffMember;
use App\Security\SecretBox;
use App\Staff\TillSession;
use App\Sync\SyncStaff;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

const CHAI = '0199b1f2-0000-7000-8000-00000000c001';
const MANDAZI = '0199b1f2-0000-7000-8000-00000000c002';

beforeEach(function () {
    Queue::fake();
    pairTill();
    openTestShift(); // selling needs an open drawer shift (cash-up)

    Item::query()->insert([
        ['id' => CHAI, 'category_id' => null, 'type' => 'product', 'name' => 'Chai', 'sku' => null, 'barcode' => '6161100000017', 'price_cents' => 5_050, 'vat_category' => 'standard', 'is_active' => true],
        ['id' => MANDAZI, 'category_id' => null, 'type' => 'product', 'name' => 'Mandazi', 'sku' => null, 'barcode' => null, 'price_cents' => 2_000, 'vat_category' => 'standard', 'is_active' => true],
    ]);

    $this->cashier = StaffMember::query()->create(SyncStaff::row(
        ['id' => '0199b1f2-0000-7000-8000-00000000d001', 'name' => 'Wanjiku', 'role' => 'cashier', 'permissions' => ['orders.take', 'payments.take'], 'pin_hash' => '$2y$04$hash', 'pin_length' => 4],
        app(SecretBox::class),
    ));
    app(TillSession::class)->signIn($this->cashier);
});

/** The payments API: POST answers pending; each GET answers the next of $statuses (the last one repeats). */
function fakePayments(array $statuses = ['pending'], int $postStatus = 202, array $postBody = []): void
{
    $calls = 0;

    Http::fake([
        TEST_API_URL.'/api/v1/payments' => fn (Request $request) => Http::response($postBody ?: ['uuid' => $request['uuid'], 'status' => 'pending', 'amount_cents' => $request['amount_cents'], 'provider_reference' => null, 'failure_reason' => null], $postStatus),
        TEST_API_URL.'/api/v1/payments/*' => function (Request $request) use (&$calls, $statuses) {
            $status = $statuses[min($calls++, count($statuses) - 1)];

            return Http::response([
                'uuid' => basename((string) parse_url($request->url(), PHP_URL_PATH)),
                'status' => $status,
                'amount_cents' => 0,
                'provider_reference' => $status === 'succeeded' ? 'TJL4XK9P2Q' : null,
                'failure_reason' => $status === 'cancelled' ? 'Request cancelled by user' : null,
            ]);
        },
    ]);
}

it('builds a sale from taps and scans, priced from the menu', function () {
    Livewire::test(Sale::class)
        ->call('add', CHAI)
        ->call('add', CHAI)
        ->call('scan', '6161100000017')
        ->call('add', MANDAZI)
        ->call('decrement', MANDAZI)
        ->assertSet('cart', [CHAI => 3])
        ->assertSee('KES 151.50')
        ->call('scan', '0000000000000')
        ->assertSee(__('terminal.sale.not_found', ['code' => '0000000000000']));
});

it('takes cash, shows the change, and records the sale with the cashier', function () {
    Livewire::test(Sale::class)
        ->call('add', CHAI)
        ->call('add', MANDAZI)
        ->call('startPayment')
        ->assertSet('step', Sale::PAY)
        ->call('press', '1')->call('press', '0')->call('press', '0')
        ->call('complete')
        ->assertSet('step', Sale::DONE)
        ->assertSee('KES 29.50')
        ->call('newSale')
        ->assertSet('cart', [])
        ->assertSet('step', Sale::CART);

    $order = Order::query()->with('tenders')->sole();

    expect($order->staff_id)->toBe($this->cashier->id)
        ->and($order->total_cents)->toBe(7_050)
        ->and($order->tenders->sole()->only(['method', 'amount_cents', 'tendered_cents', 'change_cents']))
        ->toBe(['method' => 'cash', 'amount_cents' => 7_050, 'tendered_cents' => 10_000, 'change_cents' => 2_950]);
});

it('refuses to complete with too little cash', function () {
    Livewire::test(Sale::class)
        ->call('add', CHAI)
        ->call('startPayment')
        ->call('quickCash', 50)
        ->call('complete')
        ->assertSet('step', Sale::PAY)
        ->assertSee(__('terminal.sale.cash_short', ['amount' => 'KES 50.50']));

    expect(Order::query()->count())->toBe(0);
});

it('offers exact cash rounded up to the shilling, then the next notes', function () {
    expect(Sale::quickCashOptions(5_050))->toBe([51, 100, 200, 500])
        ->and(Sale::quickCashOptions(100_000))->toBe([1000, 1050, 1100, 1200])
        ->and(Sale::quickCashOptions(0))->toBe([]);
});

it('takes whole shillings by M-Pesa and the cents in cash, linking the payment to the sale', function () {
    fakePayments(['pending', 'succeeded']);

    $screen = Livewire::test(Sale::class)
        ->call('add', CHAI)
        ->call('startPayment')
        ->assertSet('mpesaAmount', '50')
        ->set('phone', '0712 345 678')
        ->call('requestMpesa')
        ->assertSet('step', Sale::MPESA)
        ->call('checkMpesa')
        ->assertSet('step', Sale::MPESA)
        ->call('checkMpesa')
        ->assertSet('step', Sale::PAY)
        ->assertSee('M-Pesa TJL4XK9P2Q')
        ->call('quickCash', 1)
        ->call('complete')
        ->assertSet('step', Sale::DONE);

    $order = Order::query()->with('tenders')->sole();
    $push = $order->tenders->firstWhere('method', 'mpesa_push');

    expect($order->id)->toBe($screen->get('saleId'))
        ->and($push?->amount_cents)->toBe(5_000)
        ->and($order->tenders->firstWhere('method', 'cash')?->amount_cents)->toBe(50);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['order_uuid'] === $order->id
        && $request['uuid'] === $push?->payment_uuid
        && $request['phone'] === '+254712345678'
        && $request['amount_cents'] === 5_000);
});

it('completes by itself when M-Pesa pays the whole sale', function () {
    fakePayments(['succeeded']);

    Livewire::test(Sale::class)
        ->call('add', MANDAZI)
        ->call('startPayment')
        ->set('phone', '0712345678')
        ->call('requestMpesa')
        ->call('checkMpesa')
        ->assertSet('step', Sale::DONE)
        ->assertSee(__('terminal.sale.done.no_change'));

    expect(Order::query()->sole()->tenders()->sole()->method)->toBe('mpesa_push');
});

it('says why M-Pesa failed and lets the cashier take cash instead', function () {
    fakePayments(['cancelled']);

    Livewire::test(Sale::class)
        ->call('add', MANDAZI)
        ->call('startPayment')
        ->set('phone', '0712345678')
        ->call('requestMpesa')
        ->call('checkMpesa')
        ->assertSet('step', Sale::PAY)
        ->assertSee('Request cancelled by user')
        ->call('quickCash', 20)
        ->call('complete')
        ->assertSet('step', Sale::DONE);
});

it('explains a refused or impossible M-Pesa request', function (Closure $server, string $message) {
    $server();

    Livewire::test(Sale::class)
        ->call('add', MANDAZI)
        ->call('startPayment')
        ->set('phone', '0712345678')
        ->call('requestMpesa')
        ->assertSet('step', Sale::PAY)
        ->assertSee(__($message));
})->with([
    'no account for the branch' => [fn () => fakePayments(postStatus: 422, postBody: ['message' => 'This branch has no account to take this payment into.', 'code' => 'payment_account_missing']), 'terminal.sale.mpesa.refused.payment_account_missing'],
    'account suspended' => [fn () => fakePayments(postStatus: 403, postBody: ['message' => 'This account is suspended.', 'code' => 'tenant_suspended']), 'terminal.sale.mpesa.refused.tenant_suspended'],
    'offline' => [fn () => Http::fake(fn () => throw new ConnectionException('Network is unreachable')), 'terminal.sale.mpesa.offline'],
]);

it('checks the phone number and amount before asking', function () {
    Http::fake();

    Livewire::test(Sale::class)
        ->call('add', MANDAZI)
        ->call('startPayment')
        ->set('phone', '12345')
        ->call('requestMpesa')
        ->assertSee(__('terminal.sale.mpesa.bad_phone'))
        ->set('phone', '0712345678')
        ->set('mpesaAmount', '21')
        ->call('requestMpesa')
        ->assertSee(__('terminal.sale.mpesa.bad_amount', ['max' => 20]));

    Http::assertNothingSent();
});

it('keeps the sale fixed once M-Pesa paid part of it', function () {
    fakePayments(['succeeded']);

    Livewire::test(Sale::class)
        ->call('add', CHAI)
        ->call('startPayment')
        ->set('phone', '0712345678')
        ->call('requestMpesa')
        ->call('checkMpesa')
        ->call('backToCart')
        ->assertSet('step', Sale::PAY)
        ->assertSee(__('terminal.sale.paid_in_part'))
        ->call('add', MANDAZI)
        ->assertSet('cart', [CHAI => 1])
        ->call('signOut')
        ->assertNoRedirect();
});

it('keeps waiting through a network blip, and offers to stop once the prompt has had time', function () {
    fakePayments(['pending']);

    $screen = Livewire::test(Sale::class)
        ->call('add', MANDAZI)
        ->call('startPayment')
        ->set('phone', '0712345678')
        ->call('requestMpesa')
        ->call('stopWaiting')
        ->assertSet('step', Sale::MPESA);

    // The screen polls every 2 seconds; here every 10, through a dropped connection.
    Http::fake(fn () => throw new ConnectionException('Network is unreachable'));

    for ($waited = 0; $waited < Sale::MPESA_PATIENCE_SECONDS; $waited += 10) {
        $screen->assertDontSee(__('terminal.sale.mpesa.stop'));
        $this->travel(10)->seconds();
        $screen->call('checkMpesa')->assertSet('step', Sale::MPESA);
    }

    $screen
        ->assertSee(__('terminal.sale.mpesa.stop'))
        ->call('stopWaiting')
        ->assertSet('step', Sale::PAY)
        ->assertSee(__('terminal.sale.mpesa.stopped'));
});

it('shows where the prompt went, and sends it again to the same number for the same amount', function () {
    fakePayments(['pending']);

    $screen = Livewire::test(Sale::class)
        ->call('add', MANDAZI)
        ->call('startPayment')
        ->set('phone', '0712 345 957')
        ->call('requestMpesa')
        ->assertSee(__('terminal.sale.mpesa.sent_to', ['phone' => '07•• ••• 957']))
        // Too soon: the first prompt may still reach the customer.
        ->call('sendAgain')
        ->assertSet('step', Sale::MPESA);

    $first = $screen->get('waiting')['uuid'];
    $this->travel(Sale::MPESA_PATIENCE_SECONDS)->seconds();

    $screen->call('$refresh')
        ->assertSee(__('terminal.sale.mpesa.send_again'))
        ->call('sendAgain')
        ->assertSet('step', Sale::MPESA);

    expect($screen->get('waiting')['uuid'])->not->toBe($first);
    $posts = Http::recorded(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/api/v1/payments'));
    expect($posts)->toHaveCount(2)
        ->and($posts->map(fn (array $pair) => [$pair[0]['phone'], $pair[0]['amount_cents'], $pair[0]['order_uuid']])->unique()->count())->toBe(1);
});

it('offers a clear way back to the order before anything is paid', function () {
    Livewire::test(Sale::class)
        ->call('add', MANDAZI)
        ->call('startPayment')
        ->assertSee(__('terminal.sale.back_to_order'))
        ->call('backToCart')
        ->assertSet('step', Sale::CART);
});

it('masks a phone number to its first two and last three digits', function (?string $phone, string $masked) {
    expect(Sale::maskedPhone($phone))->toBe($masked);
})->with([
    'kenyan mobile' => ['+254712345957', '07•• ••• 957'],
    'airtel' => ['+254733000123', '07•• ••• 123'],
    'nothing' => [null, ''],
]);

it('takes a typed M-Pesa code offline, with the cents in cash', function () {
    Http::fake(fn () => throw new ConnectionException('Network is unreachable'));

    Livewire::test(Sale::class)
        ->call('add', CHAI)
        ->call('startPayment')
        ->assertSet('codeAmount', '50')
        ->set('typedCode', 'tjl7 qw2e9r')
        ->call('addTypedCode')
        ->assertSee('M-Pesa code TJL7QW2E9R')
        ->call('quickCash', 1)
        ->call('complete')
        ->assertSet('step', Sale::DONE);

    $tenders = Order::query()->sole()->tenders()->get();

    expect($tenders->firstWhere('method', 'mpesa_code')?->only(['amount_cents', 'mpesa_reference', 'payment_uuid']))
        ->toBe(['amount_cents' => 5_000, 'mpesa_reference' => 'TJL7QW2E9R', 'payment_uuid' => null])
        ->and($tenders->firstWhere('method', 'cash')?->amount_cents)->toBe(50);
});

it('completes by itself when a typed code pays the whole sale', function () {
    Livewire::test(Sale::class)
        ->call('add', MANDAZI)
        ->call('startPayment')
        ->set('typedCode', 'TJL7QW2E9R')
        ->call('addTypedCode')
        ->assertSet('step', Sale::DONE);
});

it('checks typed codes and lets a mistyped one be removed', function () {
    Livewire::test(Sale::class)
        ->call('add', CHAI)
        ->call('add', CHAI)
        ->call('startPayment')
        ->set('typedCode', 'TJL7QW')
        ->call('addTypedCode')
        ->assertSee(__('terminal.sale.code.bad_code'))
        ->set('typedCode', 'TJL7QW2E9R')
        ->set('codeAmount', '50')
        ->call('addTypedCode')
        ->set('typedCode', 'TJL7QW2E9R')
        ->set('codeAmount', '20')
        ->call('addTypedCode')
        ->assertSee(__('terminal.sale.code.duplicate'))
        ->call('removeTypedCode', 0)
        ->assertSet('mpesaPaid', [])
        ->assertSet('codeAmount', '101');
});

it('lets a waiter build a sale but not take the payment', function () {
    $waiter = StaffMember::query()->create(SyncStaff::row(
        ['id' => '0199b1f2-0000-7000-8000-00000000d002', 'name' => 'Otieno', 'role' => 'waiter', 'permissions' => ['orders.take'], 'pin_hash' => '$2y$04$hash', 'pin_length' => 4],
        app(SecretBox::class),
    ));
    app(TillSession::class)->signIn($waiter);

    Livewire::test(Sale::class)
        ->call('add', MANDAZI)
        ->call('startPayment')
        ->assertSet('step', Sale::CART)
        ->assertSee(__('terminal.sale.refused.not_allowed'));
});
