<?php

declare(strict_types=1);

use App\Jobs\PrintBillJob;
use App\Jobs\PushTicketsJob;
use App\Jobs\ReleaseTicketHoldJob;
use App\Livewire\Sale;
use App\Models\DiningTable;
use App\Models\FloorArea;
use App\Models\Item;
use App\Models\Order;
use App\Models\StaffMember;
use App\Models\Ticket;
use App\Models\TicketLine;
use App\Printing\PrinterConnections;
use App\Printing\PrinterSettings;
use App\Printing\ReceiptPrinter;
use App\Security\SecretBox;
use App\Staff\TillSession;
use App\Sync\SyncStaff;
use App\Sync\SyncTickets;
use App\Tickets\PrepareBillQr;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\RecordingPrinterConnections;

const TUSKER = '0199b1f2-0000-7000-8000-00000000f001';
const SODA = '0199b1f2-0000-7000-8000-00000000f002';
const TABLE_4 = '0199b1f2-0000-7000-8000-00000000f104';

/** An 8×8 PNG, standing in for Safaricom's QR. */
const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAIAQMAAAD+wSzIAAAABlBMVEX///8AAABVwtN+AAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADklEQVQImWP4wACBUAAALRADwQ6OuycAAAAASUVORK5CYII=';

/** GS v 0: a raster image on ESC/POS. */
const GS_V_0 = "\x1Dv0";

beforeEach(function () {
    Queue::fake();
    pairTill(); // a table-service branch
    openTestShift(); // selling needs an open drawer shift (cash-up)
    $this->travelTo('2026-10-27T10:05:00Z'); // 13:05 in Nairobi

    Item::query()->insert([
        ['id' => TUSKER, 'category_id' => null, 'type' => 'product', 'name' => 'Tusker', 'sku' => null, 'barcode' => null, 'price_cents' => 35_000, 'vat_category' => 'standard', 'is_active' => true],
        ['id' => SODA, 'category_id' => null, 'type' => 'product', 'name' => 'Soda', 'sku' => null, 'barcode' => null, 'price_cents' => 8_000, 'vat_category' => 'standard', 'is_active' => true],
    ]);
    FloorArea::query()->create(['id' => '0199b1f2-0000-7000-8000-00000000f100', 'name' => 'Main hall', 'sort_order' => 1]);
    DiningTable::query()->create(['id' => TABLE_4, 'floor_area_id' => '0199b1f2-0000-7000-8000-00000000f100', 'name' => '4', 'seats' => 4, 'sort_order' => 1]);

    $make = fn (string $role, array $permissions) => StaffMember::query()->create(SyncStaff::row(
        ['id' => (string) Str::uuid(), 'name' => ucfirst($role).' Otieno', 'role' => $role, 'permissions' => $permissions, 'pin_hash' => '$2y$04$hash', 'pin_length' => 4],
        app(SecretBox::class),
    ));
    $this->waiter = $make('waiter', ['orders.take']);
    $this->cashier = $make('cashier', ['orders.take', 'payments.take']);
    app(TillSession::class)->signIn($this->waiter);
});

/** A waiter sends two Tuskers to table 4 and gets a fresh screen. */
function sendTwoTuskersToTable4(): Ticket
{
    Livewire::test(Sale::class)
        ->call('add', TUSKER)->call('add', TUSKER)
        ->call('sendOrder')
        ->assertSet('step', Sale::TABLE)
        ->call('sendTo', TABLE_4)
        ->assertSet('step', Sale::CART)
        ->assertSet('cart', [])
        ->assertSet('ticketId', null)
        ->assertSee(__('terminal.tickets.sent', ['ticket' => __('terminal.tickets.table', ['name' => '4'])]));

    return Ticket::query()->with('lines')->sole();
}

it('lets a waiter send an order to a table, written on the till first and pushed after', function () {
    $ticket = sendTwoTuskersToTable4();

    expect($ticket->only(['table_id', 'table_name', 'status', 'sync_status', 'staff_id']))->toBe([
        'table_id' => TABLE_4, 'table_name' => '4', 'status' => 'open', 'sync_status' => 'pending', 'staff_id' => $this->waiter->id,
    ])
        ->and($ticket->lines->pluck('quantity')->all())->toBe([2])
        ->and($ticket->lines->first()?->unit_price_cents)->toBe(35_000)
        ->and($ticket->totals()->total->cents)->toBe(70_000);

    Queue::assertPushed(PushTicketsJob::class);
});

it('takes a takeaway order with an optional name', function () {
    Livewire::test(Sale::class)
        ->call('add', SODA)
        ->call('sendOrder')
        ->set('takeawayName', '  Achieng ')
        ->call('sendTo')
        ->assertSee(__('terminal.tickets.sent', ['ticket' => __('terminal.tickets.takeaway_for', ['name' => 'Achieng'])]));

    expect(Ticket::query()->sole()->only(['table_id', 'label']))->toBe(['table_id' => null, 'label' => 'Achieng']);
});

it('keeps sent lines locked at the price they were sent at, and adds the next round', function () {
    $ticket = sendTwoTuskersToTable4();
    Item::query()->whereKey(TUSKER)->update(['price_cents' => 40_000]);

    $screen = Livewire::test(Sale::class)
        ->call('showTickets')
        ->assertSee(__('terminal.tickets.table', ['name' => '4']))
        ->call('openTicket', $ticket->id)
        ->assertSee(__('terminal.tickets.sent_line'))
        ->call('add', SODA)
        ->assertSee('KES 780.00') // 2 × 350 sent + 80 new
        ->call('sendOrder');

    $screen->assertSet('ticketId', null);
    expect(TicketLine::query()->orderBy('name')->pluck('unit_price_cents', 'name')->all())->toBe(['Soda' => 8_000, 'Tusker' => 35_000]);
});

it('does not let a waiter take payment', function () {
    $ticket = sendTwoTuskersToTable4();

    Livewire::test(Sale::class)
        ->call('showTickets')->call('openTicket', $ticket->id)
        ->assertDontSee(__('terminal.sale.pay'))
        ->call('startPayment')
        ->assertSet('step', Sale::CART)
        ->assertSee(__('terminal.sale.refused.not_allowed'));
});

it('prints the bill, not a tax invoice, and marks the ticket billed', function () {
    RecordingPrinterConnections::$jobs = [];
    $this->app->bind(PrinterConnections::class, RecordingPrinterConnections::class);
    (new PrinterSettings(PrinterSettings::DEVICE, '/dev/usb/lp0', 80))->save();
    $ticket = sendTwoTuskersToTable4();

    Livewire::test(Sale::class)
        ->call('showTickets')->call('openTicket', $ticket->id)
        ->call('printBill')
        ->assertSee(__('terminal.tickets.bill_printed', ['ticket' => __('terminal.tickets.table', ['name' => '4'])]));

    expect($ticket->refresh()->only(['status', 'sync_status']))->toBe(['status' => 'bill_requested', 'sync_status' => 'pending']);
    Queue::assertPushed(PrintBillJob::class, fn (PrintBillJob $job) => $job->ticketId === $ticket->id);

    app(ReceiptPrinter::class)->bill($ticket);
    expect(RecordingPrinterConnections::$jobs[0])
        ->toContain('BILL')
        ->toContain('Not a tax invoice')
        ->toContain('Table 4')
        ->toContain('2 x Tusker')
        ->toContain(ReceiptPrinter::row('Total excl. VAT', '603.45', 48))
        ->toContain(ReceiptPrinter::row('VAT 16% on 603.45', '96.55', 48))
        ->toContain(ReceiptPrinter::row('TO PAY', 'KES 700.00', 48))
        ->not->toContain('PIN');
});

it('lets the cashier pay a ticket at its sent prices, closing it and naming it on the sale', function () {
    $ticket = sendTwoTuskersToTable4();
    Item::query()->whereKey(TUSKER)->update(['price_cents' => 40_000]);
    app(TillSession::class)->signIn($this->cashier);

    Livewire::test(Sale::class)
        ->call('showTickets')->call('openTicket', $ticket->id)
        ->call('add', SODA) // one more round, sent with the payment
        ->call('startPayment')
        ->assertSet('step', Sale::PAY)
        ->assertSee('KES 780.00')
        ->call('quickCash', 1000)
        ->call('complete')
        ->assertSet('step', Sale::DONE);

    $order = Order::query()->with('lines')->sole();

    expect($order->ticket_id)->toBe($ticket->id)
        ->and($order->total_cents)->toBe(78_000)
        ->and($order->lines->pluck('unit_price_cents', 'name')->all())->toBe(['Tusker' => 35_000, 'Soda' => 8_000])
        ->and($order->toSyncPayload()['ticket_uuid'])->toBe($ticket->id)
        ->and($ticket->refresh()->only(['status', 'order_id']))->toBe(['status' => 'closed', 'order_id' => $order->id])
        ->and(Ticket::query()->unpaid()->count())->toBe(0)
        ->and($order->only(['receipt_number', 'table_name']))->toBe(['receipt_number' => 'T1-000001', 'table_name' => '4']);

    // The receipt names the table, even once the ticket itself is gone from the till.
    RecordingPrinterConnections::$jobs = [];
    $this->app->bind(PrinterConnections::class, RecordingPrinterConnections::class);
    (new PrinterSettings(PrinterSettings::DEVICE, '/dev/usb/lp0', 80))->save();
    Ticket::query()->delete();

    app(ReceiptPrinter::class)->receipt($order->refresh());
    expect(RecordingPrinterConnections::$jobs[0])->toContain('Receipt No. T1-000001')->toContain('Table 4');
});

it('sends a quick sale with no ticket on it', function () {
    app(TillSession::class)->signIn($this->cashier);

    Livewire::test(Sale::class)->call('add', SODA)->call('startPayment')->call('quickCash', 80)->call('complete')->assertSet('step', Sale::DONE);

    expect(Order::query()->sole()->toSyncPayload())->not->toHaveKey('ticket_uuid');
});

describe('syncing tickets', function () {
    /*
     * The tickets API, registered once: GET lists $this->server; each POSTed
     * batch is kept in $this->sent and answered per ticket by $this->answer.
     */
    beforeEach(function () {
        $this->sent = new ArrayObject;
        $this->server = [];
        $this->answer = fn (array $ticket) => ['uuid' => $ticket['uuid'], 'status' => 'accepted', 'ticket_status' => 'open'];

        Http::fake([
            TEST_API_URL.'/api/v1/tickets/sync' => function (Request $request) {
                test()->sent->append($request['tickets']);

                return Http::response(['results' => array_map(test()->answer, $request['tickets'])]);
            },
            TEST_API_URL.'/api/v1/tickets' => fn () => Http::response(['tickets' => test()->server]),
        ]);
    });

    it('sends only the lines the server does not have yet, and marks them sent', function () {
        $ticket = sendTwoTuskersToTable4();

        app(SyncTickets::class)->push();
        expect($this->sent[0][0]['lines'])->toHaveCount(1)
            ->and($this->sent[0][0]['table_id'])->toBe(TABLE_4)
            ->and($ticket->refresh()->sync_status)->toBe('synced')
            ->and(TicketLine::query()->pluck('sync_status')->all())->toBe(['synced']);

        // Nothing new: nothing sent.
        app(SyncTickets::class)->push();
        expect($this->sent)->toHaveCount(1);
    });

    it('keeps lines the server refused because the ticket was already paid, for a manager', function () {
        $ticket = sendTwoTuskersToTable4();
        $this->answer = fn (array $sent) => ['uuid' => $sent['uuid'], 'status' => 'duplicate', 'ticket_status' => 'closed', 'refused_lines' => array_column($sent['lines'], 'uuid')];

        app(SyncTickets::class)->handle();

        expect($ticket->refresh()->only(['status', 'sync_status', 'sync_reason']))->toBe(['status' => 'closed', 'sync_status' => 'rejected', 'sync_reason' => SyncTickets::LINES_AFTER_PAYMENT])
            ->and(TicketLine::query()->sole()->sync_status)->toBe('refused');

        Livewire::test(Sale::class)->call('showTickets')->assertSee(__('terminal.tickets.rejected.lines_after_payment'));
    });

    it('copies the branch’s tickets from other tills, and drops ones paid elsewhere', function () {
        $this->server = [[
            'id' => '0199b1f2-0000-7000-8000-0000000a0001', 'status' => 'bill_requested', 'table_id' => TABLE_4, 'table_name' => '4', 'label' => null,
            'staff_id' => $this->waiter->id, 'opened_at' => '2026-10-27T09:50:00+00:00', 'bill_requested_at' => '2026-10-27T10:00:00+00:00', 'total_cents' => 35_000,
            'lines' => [['id' => '0199b1f2-0000-7000-8000-0000000b0001', 'item_id' => TUSKER, 'name' => 'Tusker', 'quantity' => 1, 'unit_price_cents' => 35_000, 'vat_category' => 'standard', 'line_total_cents' => 35_000, 'staff_id' => $this->waiter->id, 'sent_at' => '2026-10-27T09:51:00+00:00']],
        ]];

        app(SyncTickets::class)->pull();
        expect(Ticket::query()->sole()->only(['status', 'table_name']))->toBe(['status' => 'bill_requested', 'table_name' => '4'])
            ->and(Ticket::query()->sole()->totals()->total->cents)->toBe(35_000);

        // Paid on another till: gone from the server's list, so gone here.
        $this->server = [];
        app(SyncTickets::class)->pull();
        expect(Ticket::query()->count())->toBe(0)->and(TicketLine::query()->count())->toBe(0);
    });

    it('never drops or overwrites a ticket the server does not have yet, or reopens one paid here', function () {
        $pending = sendTwoTuskersToTable4();
        $paidHere = Ticket::query()->create(['id' => (string) Str::uuid7(), 'staff_id' => $this->waiter->id, 'status' => 'closed', 'opened_at' => now(), 'sync_status' => 'synced']);

        $this->server = [[
            'id' => $paidHere->id, 'status' => 'open', 'table_id' => null, 'table_name' => null, 'label' => null,
            'staff_id' => $this->waiter->id, 'opened_at' => now()->toIso8601String(), 'bill_requested_at' => null, 'total_cents' => 0, 'lines' => [],
        ]];

        app(SyncTickets::class)->pull();

        expect(Ticket::query()->find($pending->id)?->sync_status)->toBe('pending')
            ->and(Ticket::query()->find($paidHere->id)?->status)->toBe('closed');
    });

    it('notes a ticket the server rejected and keeps its lines to send again', function () {
        $ticket = sendTwoTuskersToTable4();
        $this->answer = fn (array $sent) => ['uuid' => $sent['uuid'], 'status' => 'rejected', 'reason' => 'unknown_table'];

        app(SyncTickets::class)->push();

        expect($ticket->refresh()->only(['sync_status', 'sync_reason']))->toBe(['sync_status' => 'rejected', 'sync_reason' => 'unknown_table'])
            ->and(TicketLine::query()->sole()->sync_status)->toBe('pending');
    });
});

describe('scan to pay on the bill', function () {
    beforeEach(function () {
        RecordingPrinterConnections::$jobs = [];
        $this->app->bind(PrinterConnections::class, RecordingPrinterConnections::class);
        (new PrinterSettings(PrinterSettings::DEVICE, '/dev/usb/lp0', 80))->save();
        $this->ticket = sendTwoTuskersToTable4();
    });

    /** The server's answer for a QR payment of the two Tuskers. */
    function qrAnswer(string $uuid, ?string $png, string $status = 'pending', ?string $reference = null): array
    {
        return [
            'uuid' => $uuid, 'order_uuid' => null, 'provider' => 'mpesa_ke', 'amount_cents' => 70_000, 'currency' => 'KES',
            'status' => $status, 'provider_reference' => $reference, 'failure_code' => null, 'failure_reason' => null, 'channel' => 'qr',
            'qr' => ['png' => $png, 'paybill' => '174379', 'account' => 'VPK7M2Q9TR'],
        ];
    }

    it('prints the QR and the paybill and account under the total, for this ticket’s sale', function () {
        Http::fake([TEST_API_URL.'/api/v1/payments' => fn (Request $request) => Http::response(qrAnswer($request['uuid'], TINY_PNG), 202)]);

        app(PrintBillJob::class, ['ticketId' => $this->ticket->id])->handle(app(ReceiptPrinter::class), app(PrepareBillQr::class));

        $ticket = $this->ticket->refresh();
        expect(RecordingPrinterConnections::$jobs[0])
            ->toContain('Scan with the M-Pesa app to pay KES 700.00')
            ->toContain(GS_V_0)
            ->toContain('Or pay to paybill 174379, account VPK7M2Q9TR')
            ->and($ticket->sale_id)->not->toBeNull()
            ->and($ticket->qr_payment_id)->not->toBeNull();
        Http::assertSent(fn (Request $request) => $request['channel'] === 'qr'
            && ! isset($request['phone'])
            && $request['amount_cents'] === 70_000
            && $request['order_uuid'] === $ticket->sale_id
            && $request['uuid'] === $ticket->qr_payment_id);
    });

    it('waits a few seconds for Safaricom’s QR, then prints the numbers alone if it never comes', function () {
        Sleep::fake();
        Http::fake([
            TEST_API_URL.'/api/v1/payments' => fn (Request $request) => Http::response(qrAnswer($request['uuid'], null), 202),
            TEST_API_URL.'/api/v1/payments/*' => fn (Request $request) => Http::response(qrAnswer(basename($request->url()), null)),
        ]);

        app(PrintBillJob::class, ['ticketId' => $this->ticket->id])->handle(app(ReceiptPrinter::class), app(PrepareBillQr::class));

        Sleep::assertSleptTimes(PrepareBillQr::WAIT_MILLISECONDS / 500);
        expect(RecordingPrinterConnections::$jobs[0])->not->toContain(GS_V_0)->toContain('Or pay to paybill 174379, account VPK7M2Q9TR');
    });

    it('prints the bill as before on a buy goods till or when offline', function (Closure $answer) {
        Http::fake([TEST_API_URL.'/api/v1/payments' => $answer]);

        app(PrintBillJob::class, ['ticketId' => $this->ticket->id])->handle(app(ReceiptPrinter::class), app(PrepareBillQr::class));

        expect(RecordingPrinterConnections::$jobs[0])->toContain('KES 700.00')->not->toContain('Scan with the M-Pesa app')->not->toContain('paybill');
    })->with([
        'buy goods' => [fn () => fn () => Http::response(['message' => 'Scan to pay needs a paybill account.', 'code' => 'qr_needs_paybill'], 422)],
        'offline' => [fn () => fn () => throw new ConnectionException('Network is unreachable')],
    ]);

    it('counts the bill’s QR as paid once confirmed, and the cashier completes the sale as that payment’s sale', function () {
        Http::fake([TEST_API_URL.'/api/v1/payments' => fn (Request $request) => Http::response(qrAnswer($request['uuid'], TINY_PNG), 202)]);
        app(PrintBillJob::class, ['ticketId' => $this->ticket->id])->handle(app(ReceiptPrinter::class), app(PrepareBillQr::class));
        $ticket = $this->ticket->refresh();
        app(TillSession::class)->signIn($this->cashier);

        $screen = Livewire::test(Sale::class)
            ->call('showTickets')->call('openTicket', $ticket->id)
            ->call('startPayment')
            ->assertSee(__('terminal.sale.qr.waiting'));

        // Not paid yet: nothing changes.
        $paid = false;
        Http::fake([TEST_API_URL.'/api/v1/payments/*' => function () use (&$paid, $ticket) {
            return Http::response($paid
                ? qrAnswer((string) $ticket->qr_payment_id, TINY_PNG, 'succeeded', 'TJL7QW2E9R')
                : qrAnswer((string) $ticket->qr_payment_id, TINY_PNG));
        }]);
        $screen->call('checkBillQr')->assertSet('mpesaPaid', []);

        $paid = true;
        $screen->call('checkBillQr')
            ->assertSee(__('terminal.sale.qr.paid_notice', ['reference' => 'TJL7QW2E9R']))
            ->assertSee(__('terminal.sale.qr.paid', ['reference' => 'TJL7QW2E9R']))
            // The cashier confirms: the sale is not completed by itself.
            ->assertSet('step', Sale::PAY)
            ->call('complete')
            ->assertSet('step', Sale::DONE);

        $order = Order::query()->with('tenders')->sole();
        expect($order->id)->toBe($ticket->sale_id)
            ->and($order->tenders->map->only(['method', 'amount_cents', 'payment_uuid'])->all())->toBe([
                ['method' => 'mpesa_push', 'amount_cents' => 70_000, 'payment_uuid' => $ticket->qr_payment_id],
            ]);
    });
});

describe('a ticket is paid once', function () {
    beforeEach(function () {
        $this->ticket = sendTwoTuskersToTable4();
        app(TillSession::class)->signIn($this->cashier);
    });

    function payTicket(Ticket $ticket): Testable
    {
        return Livewire::test(Sale::class)->call('showTickets')->call('openTicket', $ticket->id)->call('startPayment');
    }

    it('asks the server to hold the ticket for this sale before taking payment', function () {
        $this->ticket->update(['on_server' => true, 'sync_status' => 'synced']);
        Http::fake([TEST_API_URL.'/api/v1/tickets/*/hold' => Http::response(['status' => 'held'])]);

        payTicket($this->ticket)->assertSet('step', Sale::PAY);

        $saleId = $this->ticket->refresh()->sale_id;
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), "/tickets/{$this->ticket->id}/hold")
            && $request['sale_uuid'] === $saleId);
    });

    it('refuses a ticket paid on another till, and drops it from this till at once', function () {
        $this->ticket->update(['on_server' => true, 'sync_status' => 'synced']);
        Http::fake([TEST_API_URL.'/api/v1/tickets/*/hold' => Http::response(['code' => 'ticket_paid'], 409)]);

        payTicket($this->ticket)
            ->assertSet('step', Sale::CART)
            ->assertSet('ticketId', null)
            ->assertSee(__('terminal.tickets.hold.ticket_paid'));

        expect($this->ticket->refresh()->status)->toBe(Ticket::CLOSED)
            ->and(Order::query()->count())->toBe(0);
    });

    it('refuses while another till is taking payment for it', function () {
        $this->ticket->update(['on_server' => true, 'sync_status' => 'synced']);
        Http::fake([TEST_API_URL.'/api/v1/tickets/*/hold' => Http::response(['code' => 'ticket_being_paid'], 409)]);

        payTicket($this->ticket)->assertSet('step', Sale::CART)->assertSee(__('terminal.tickets.hold.ticket_being_paid'));
    });

    it('needs the internet to pay a ticket other tills can see, but not one only this till has', function () {
        Http::fake(fn () => throw new ConnectionException('Network is unreachable'));

        // Sent from this till and never reached the server: no other till can have paid it.
        payTicket($this->ticket)->assertSet('step', Sale::PAY);

        $this->ticket->update(['on_server' => true]);
        payTicket($this->ticket)->assertSet('step', Sale::CART)->assertSee(__('terminal.tickets.hold.needs_internet'));
    });

    it('sends a ticket the server does not have yet, then holds it', function () {
        $holds = 0;
        Http::fake([
            TEST_API_URL.'/api/v1/tickets/*/hold' => function () use (&$holds) {
                return ++$holds === 1 ? Http::response(['code' => 'unknown_ticket'], 404) : Http::response(['status' => 'held']);
            },
            TEST_API_URL.'/api/v1/tickets/sync' => fn (Request $request) => Http::response(['results' => array_map(
                fn (array $ticket) => ['uuid' => $ticket['uuid'], 'status' => 'accepted', 'ticket_status' => 'open'],
                $request['tickets'],
            )]),
        ]);

        payTicket($this->ticket)->assertSet('step', Sale::PAY);

        expect($holds)->toBe(2)->and($this->ticket->refresh()->on_server)->toBeTrue();
    });

    it('lets the hold go when the cashier goes back without taking payment', function () {
        Http::fake([TEST_API_URL.'/api/v1/tickets/*/hold' => Http::response(['status' => 'held'])]);

        payTicket($this->ticket)->call('backToCart')->assertSet('step', Sale::CART);

        Queue::assertPushed(ReleaseTicketHoldJob::class, fn (ReleaseTicketHoldJob $job) => $job->ticketId === $this->ticket->id && $job->saleId === $this->ticket->refresh()->sale_id);
    });
});
