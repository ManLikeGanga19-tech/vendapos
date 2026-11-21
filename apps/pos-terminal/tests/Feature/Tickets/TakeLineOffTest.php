<?php

declare(strict_types=1);

use App\Jobs\PrintTicketVoidSlipJob;
use App\Jobs\PushTicketsJob;
use App\Livewire\Sale;
use App\Livewire\TakeLineOff;
use App\Models\DiningTable;
use App\Models\FloorArea;
use App\Models\Item;
use App\Models\Order;
use App\Models\StaffMember;
use App\Models\Ticket;
use App\Models\TicketLine;
use App\Models\TicketLineVoid;
use App\Printing\PrinterConnections;
use App\Printing\PrinterSettings;
use App\Printing\ReceiptPrinter;
use App\Security\SecretBox;
use App\Staff\TillSession;
use App\Sync\SyncStaff;
use App\Sync\SyncTickets;
use App\Tickets\SendOrder;
use App\Tickets\TicketRefused;
use App\Tickets\VoidedTicketLine;
use App\Tickets\VoidTicketLine;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\RecordingPrinterConnections;
use VendaPOS\Core\Enums\VoidReason;

const OFF_TUSKER = '0199b1f2-0000-7000-8000-00000000e001';
const OFF_SODA = '0199b1f2-0000-7000-8000-00000000e002';
const OFF_TABLE = '0199b1f2-0000-7000-8000-00000000e104';
const OFF_MANAGER_PIN = '258046';

beforeEach(function () {
    Queue::fake();
    pairTill(); // a table-service branch
    $this->travelTo('2026-10-27T10:05:00Z'); // 13:05 in Nairobi

    Item::query()->insert([
        ['id' => OFF_TUSKER, 'category_id' => null, 'type' => 'product', 'name' => 'Tusker', 'sku' => null, 'barcode' => null, 'price_cents' => 35_000, 'vat_category' => 'standard', 'is_active' => true],
        ['id' => OFF_SODA, 'category_id' => null, 'type' => 'product', 'name' => 'Soda', 'sku' => null, 'barcode' => null, 'price_cents' => 8_000, 'vat_category' => 'standard', 'is_active' => true],
    ]);
    FloorArea::query()->create(['id' => '0199b1f2-0000-7000-8000-00000000e100', 'name' => 'Main hall', 'sort_order' => 1]);
    DiningTable::query()->create(['id' => OFF_TABLE, 'floor_area_id' => '0199b1f2-0000-7000-8000-00000000e100', 'name' => '4', 'seats' => 4, 'sort_order' => 1]);

    $member = fn (string $id, string $name, string $role, array $permissions, string $pinHash, int $pinLength) => StaffMember::query()->create(SyncStaff::row(
        ['id' => $id, 'name' => $name, 'role' => $role, 'permissions' => $permissions, 'pin_hash' => $pinHash, 'pin_length' => $pinLength],
        app(SecretBox::class),
    ));
    $this->waiter = $member('0199b1f2-0000-7000-8000-00000000e201', 'Otieno', 'waiter', ['orders.take'], '$2y$04$hash', 4);
    $this->cashier = $member('0199b1f2-0000-7000-8000-00000000e202', 'Wanjiku', 'cashier', ['orders.take', 'payments.take'], '$2y$04$hash', 4);
    $this->manager = $member('0199b1f2-0000-7000-8000-00000000e203', 'Baraka', 'manager', ['orders.take', 'payments.take', 'orders.void'], password_hash(OFF_MANAGER_PIN, PASSWORD_BCRYPT, ['cost' => 4]), 6);
    app(TillSession::class)->signIn($this->waiter);

    // Three Tusker and a soda at table 4.
    $this->ticket = app(SendOrder::class)->handle($this->waiter, [['item_id' => OFF_TUSKER, 'quantity' => 3], ['item_id' => OFF_SODA, 'quantity' => 1]], tableId: OFF_TABLE);
    $this->tuskers = $this->ticket->lines->firstWhere('name', 'Tusker');
    $this->soda = $this->ticket->lines->firstWhere('name', 'Soda');
});

/** Through the screen: how many, why, the manager, and the manager's PIN. */
function takeOff(TicketLine $line, int $quantity = 1, string $reason = 'changed_mind', string $pin = OFF_MANAGER_PIN): Testable
{
    $screen = Livewire::test(TakeLineOff::class, ['ticketId' => $line->ticket_id, 'lineId' => $line->id]);

    if ($line->remainingQuantity() > 1) {
        $screen->assertSet('step', TakeLineOff::QUANTITY);

        for ($i = 1; $i < $quantity; $i++) {
            $screen->call('more');
        }

        $screen->call('confirmQuantity');
    }

    $screen->assertSet('step', TakeLineOff::REASON)
        ->call('pickReason', $reason)
        ->assertSet('step', TakeLineOff::APPROVER)
        ->call('chooseApprover', test()->manager->id)
        ->assertSet('step', TakeLineOff::PIN);

    foreach (str_split($pin) as $digit) {
        $screen->call('press', $digit);
    }

    return $screen;
}

it('lets a waiter take one Tusker off with the manager’s PIN, offline first, printing nothing until kitchen printing', function () {
    $this->ticket->update(['status' => Ticket::BILL_REQUESTED, 'sync_status' => Ticket::SYNCED]);

    takeOff($this->tuskers)->assertRedirect(route('home', ['ticket' => $this->ticket->id]));

    $void = TicketLineVoid::query()->sole();
    expect($void->only(['ticket_line_id', 'quantity', 'voided_by', 'approved_by', 'reason', 'sync_status']))->toBe([
        'ticket_line_id' => $this->tuskers->id, 'quantity' => 1, 'voided_by' => $this->waiter->id, 'approved_by' => $this->manager->id, 'reason' => 'changed_mind', 'sync_status' => 'pending',
    ])
        // The printed bill is out of date: the ticket is open again, to be sent.
        ->and($this->ticket->refresh()->only(['status', 'sync_status']))->toBe(['status' => 'open', 'sync_status' => 'pending'])
        ->and($this->ticket->load('lines')->totals()->total->cents)->toBe(2 * 35_000 + 8_000)
        ->and($this->ticket->toSyncPayload()['voids'])->toBe([$void->toSyncPayload()]);

    Queue::assertPushed(PushTicketsJob::class);
    Queue::assertNotPushed(PrintTicketVoidSlipJob::class);
});

it('comes back to the same ticket, saying what came off', function () {
    session()->flash('notice', 'Taken off.');
    TicketLineVoid::query()->create(['id' => '0199b1f2-0000-7000-8000-00000000e301', 'ticket_id' => $this->ticket->id, 'ticket_line_id' => $this->tuskers->id, 'quantity' => 1, 'voided_at' => now(), 'voided_by' => $this->waiter->id, 'approved_by' => $this->manager->id, 'reason' => 'wrong_item', 'sync_status' => 'pending']);

    Livewire::withQueryParams(['ticket' => $this->ticket->id])->test(Sale::class)
        ->assertSet('ticketId', $this->ticket->id)
        ->assertSee('Taken off.')
        ->assertSee(__('terminal.tickets.void.voided_count', ['count' => 1]))
        ->assertSee('KES 780.00') // 2 × 350 + 80
        ->assertSee(route('tickets.take-off', ['ticketId' => $this->ticket->id, 'lineId' => $this->soda->id]));
});

it('takes a whole line off, and the cashier is paid for what is left', function () {
    takeOff($this->tuskers, 3, 'wrong_item');
    takeOff($this->soda, reason: 'duplicate'); // one soda: no count to pick

    app(TillSession::class)->signIn($this->cashier);
    Livewire::withQueryParams(['ticket' => $this->ticket->id])->test(Sale::class)
        ->assertDontSee(route('tickets.take-off', ['ticketId' => $this->ticket->id, 'lineId' => $this->tuskers->id]))
        ->call('startPayment')
        ->assertSee(__('terminal.sale.empty'));

    expect(Order::query()->count())->toBe(0)
        ->and($this->ticket->load('lines')->billedLines())->toBe([]);
});

it('charges the sale only for what is left on the ticket', function () {
    takeOff($this->tuskers, 2);
    app(TillSession::class)->signIn($this->cashier);

    Livewire::withQueryParams(['ticket' => $this->ticket->id])->test(Sale::class)
        ->call('startPayment')
        ->assertSee('KES 430.00') // 1 × 350 + 80
        ->call('quickCash', 500)
        ->call('complete')
        ->assertSet('step', Sale::DONE);

    expect(Order::query()->with('lines')->sole()->lines->pluck('quantity', 'name')->all())->toBe(['Tusker' => 1, 'Soda' => 1]);
});

it('keeps the line when the PIN is wrong, never offers "payment problem", and asks what happened for "other"', function () {
    takeOff($this->soda, pin: '111111')->assertSet('step', TakeLineOff::PIN)->assertSee(trans_choice('terminal.sign_in.wrong', 4, ['count' => 4]));

    Livewire::test(TakeLineOff::class, ['ticketId' => $this->ticket->id, 'lineId' => $this->soda->id])
        ->assertDontSee(__('terminal.void_reasons.payment_problem'))
        ->call('pickReason', 'payment_problem')
        ->assertSet('reason', null)
        ->call('pickReason', 'other')
        ->assertSet('step', TakeLineOff::REASON)
        ->call('confirmReason')
        ->assertHasErrors('note')
        ->set('note', 'Guest left')
        ->call('confirmReason')
        ->assertSet('step', TakeLineOff::APPROVER);

    expect(TicketLineVoid::query()->count())->toBe(0);
    Queue::assertNotPushed(PrintTicketVoidSlipJob::class);
});

it('never takes off more than is left, nor without someone who may approve', function () {
    $off = app(VoidTicketLine::class);

    expect(fn () => $off->handle($this->waiter, $this->manager, $this->ticket->id, $this->tuskers->id, 4, VoidReason::WrongItem, null))->toThrow(TicketRefused::class, 'too_many')
        ->and(fn () => $off->handle($this->waiter, $this->cashier, $this->ticket->id, $this->tuskers->id, 1, VoidReason::WrongItem, null))->toThrow(TicketRefused::class, 'approver_not_allowed')
        ->and(fn () => $off->handle($this->waiter, $this->manager, $this->ticket->id, $this->tuskers->id, 1, VoidReason::PaymentProblem, null))->toThrow(TicketRefused::class, 'note_needed');

    $this->ticket->update(['status' => Ticket::CLOSED]);
    expect(fn () => $off->handle($this->waiter, $this->manager, $this->ticket->id, $this->tuskers->id, 1, VoidReason::WrongItem, null))->toThrow(TicketRefused::class, 'paid');

    // Nothing left of the line: the screen sends them back to the ticket.
    $this->ticket->update(['status' => Ticket::OPEN]);
    $off->handle($this->waiter, $this->manager, $this->ticket->id, $this->soda->id, 1, VoidReason::WrongItem, null);
    Livewire::test(TakeLineOff::class, ['ticketId' => $this->ticket->id, 'lineId' => $this->soda->id])->assertRedirect(route('home', ['ticket' => $this->ticket->id]));
});

it('keeps someone who cannot take orders off the screen', function () {
    $this->waiter->update(['permissions' => []]);
    app(TillSession::class)->signIn($this->waiter->refresh());

    Livewire::test(TakeLineOff::class, ['ticketId' => $this->ticket->id, 'lineId' => $this->soda->id])->assertForbidden();
});

describe('syncing', function () {
    beforeEach(function () {
        $this->sent = new ArrayObject;
        $this->server = [];
        $this->answer = fn (array $ticket) => ['uuid' => $ticket['uuid'], 'status' => 'updated', 'ticket_status' => 'open'];

        Http::fake([
            TEST_API_URL.'/api/v1/tickets/sync' => function (Request $request) {
                test()->sent->append($request['tickets']);

                return Http::response(['results' => array_map(test()->answer, $request['tickets'])]);
            },
            TEST_API_URL.'/api/v1/tickets' => fn () => Http::response(['tickets' => test()->server]),
        ]);

        app(SyncTickets::class)->push(); // the lines are on the server
    });

    it('sends the void with the ticket, once', function () {
        takeOff($this->tuskers);

        app(SyncTickets::class)->push();
        app(SyncTickets::class)->push();

        expect($this->sent)->toHaveCount(2)
            ->and($this->sent[1][0]['lines'])->toBe([])
            ->and($this->sent[1][0]['voids'])->toHaveCount(1)
            ->and($this->sent[1][0]['voids'][0])->toMatchArray(['line_uuid' => $this->tuskers->id, 'quantity' => 1, 'reason' => 'changed_mind', 'approved_by' => $this->manager->id])
            ->and(TicketLineVoid::query()->sole()->sync_status)->toBe('synced');
    });

    it('puts the line back when the server refuses the void, and says so', function () {
        takeOff($this->tuskers);
        $this->answer = fn (array $sent) => ['uuid' => $sent['uuid'], 'status' => 'duplicate', 'ticket_status' => 'open', 'refused_voids' => array_column($sent['voids'], 'uuid')];

        app(SyncTickets::class)->push();

        expect(TicketLineVoid::query()->sole()->sync_status)->toBe('refused')
            ->and($this->ticket->load('lines')->totals()->total->cents)->toBe(3 * 35_000 + 8_000);

        Livewire::withQueryParams(['ticket' => $this->ticket->id])->test(Sale::class)
            ->assertSee(__('terminal.tickets.void.not_taken_off', ['quantity' => 1, 'item' => 'Tusker']));
    });

    it('copies what another till took off', function () {
        $this->server = [[
            'id' => $this->ticket->id, 'status' => 'open', 'table_id' => OFF_TABLE, 'table_name' => '4', 'label' => null,
            'staff_id' => $this->waiter->id, 'opened_at' => '2026-10-27T10:05:00+00:00', 'bill_requested_at' => null, 'total_cents' => 78_000,
            'lines' => [],
            'voids' => [['id' => '0199b1f2-0000-7000-8000-00000000e401', 'line_id' => $this->tuskers->id, 'quantity' => 2, 'voided_at' => '2026-10-27T10:04:00+00:00', 'voided_by' => $this->waiter->id, 'approved_by' => $this->manager->id, 'reason' => 'other', 'note' => 'Spilled']],
        ]];

        app(SyncTickets::class)->pull();
        app(SyncTickets::class)->pull();

        expect(TicketLineVoid::query()->sole()->only(['quantity', 'note', 'sync_status']))->toBe(['quantity' => 2, 'note' => 'Spilled', 'sync_status' => 'synced'])
            ->and($this->ticket->load('lines')->totals()->total->cents)->toBe(35_000 + 8_000);
    });
});

it('prints the kitchen’s VOID slip: what came off which table, what is still to make, why and who approved it', function () {
    RecordingPrinterConnections::$jobs = [];
    $this->app->bind(PrinterConnections::class, RecordingPrinterConnections::class);
    (new PrinterSettings(PrinterSettings::DEVICE, '/dev/usb/lp0', 80))->save();

    (new PrintTicketVoidSlipJob(new VoidedTicketLine(
        ticketId: $this->ticket->id,
        ticketTitle: 'Table 4',
        name: 'Tusker',
        quantity: 1,
        left: 2,
        voidedAt: CarbonImmutable::parse('2026-10-27T10:20:00Z'),
        reason: 'changed_mind',
        note: null,
        voidedBy: 'Otieno',
        approvedBy: 'Baraka',
    )))->handle(app(ReceiptPrinter::class));

    expect(RecordingPrinterConnections::$jobs[0])
        ->toContain('*** VOID ***')
        ->toContain('Table 4')
        ->toContain(ReceiptPrinter::row('Voided', '27/10/2026 13:20', 48))
        ->toContain('1 x Tusker OFF')
        ->toContain('Still to make: 2')
        ->toContain('Reason: Customer changed their mind')
        ->toContain('Voided by: Otieno')
        ->toContain('Approved by: Baraka')
        ->not->toContain("\x1bp"); // no drawer: no money moves
});

it('prints the bill for what is left', function () {
    RecordingPrinterConnections::$jobs = [];
    $this->app->bind(PrinterConnections::class, RecordingPrinterConnections::class);
    (new PrinterSettings(PrinterSettings::DEVICE, '/dev/usb/lp0', 80))->save();
    takeOff($this->tuskers);

    app(ReceiptPrinter::class)->bill($this->ticket->load('lines'));

    expect(RecordingPrinterConnections::$jobs[0])
        ->toContain(ReceiptPrinter::row('2 x Tusker', '700.00', 48))
        ->toContain(ReceiptPrinter::row('TO PAY', 'KES 780.00', 48));
});
