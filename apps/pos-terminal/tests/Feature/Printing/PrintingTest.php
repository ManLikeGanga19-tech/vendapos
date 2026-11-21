<?php

declare(strict_types=1);

use App\Jobs\PrintBillJob;
use App\Jobs\PrintReceiptJob;
use App\Jobs\PrintVoidSlipJob;
use App\Livewire\PrinterSetup;
use App\Livewire\Sale;
use App\Models\Item;
use App\Models\Order;
use App\Models\ReceiptReprint;
use App\Models\StaffMember;
use App\Models\SyncState;
use App\Printing\PrinterConnections;
use App\Printing\PrinterSettings;
use App\Printing\ReceiptPrinter;
use App\Sales\VoidedSale;
use App\Security\SecretBox;
use App\Staff\TillSession;
use App\Sync\SyncStaff;
use App\Till\Till;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\RecordingPrinterConnections;

const PRINT_CHAI = '0199b1f2-0000-7000-8000-00000000e001';

const PRINT_WATER = '0199b1f2-0000-7000-8000-00000000e002';

const PRINT_BREAD = '0199b1f2-0000-7000-8000-00000000e003';

/** ESC p: the pulse that opens a cash drawer wired to the printer. */
const DRAWER_PULSE = "\x1bp";

/** GS V: cut the paper. */
const PAPER_CUT = "\x1dV";

beforeEach(function () {
    RecordingPrinterConnections::$jobs = [];
    RecordingPrinterConnections::$unplugged = false;
    $this->app->bind(PrinterConnections::class, RecordingPrinterConnections::class);
    $this->travelTo('2026-10-26T11:05:00Z'); // 14:05 in Nairobi

    pairTill();
    Item::query()->insert(['id' => PRINT_CHAI, 'category_id' => null, 'type' => 'product', 'name' => 'Chai ya tangawizi', 'sku' => null, 'barcode' => null, 'price_cents' => 5_050, 'vat_category' => 'standard', 'is_active' => true]);

    $this->staffMember = fn (string $role, array $permissions) => StaffMember::query()->create(SyncStaff::row(
        ['id' => (string) Str::uuid(), 'name' => ucfirst($role).' Njeri', 'role' => $role, 'permissions' => $permissions, 'pin_hash' => '$2y$04$hash', 'pin_length' => 4],
        app(SecretBox::class),
    ));
    $this->cashier = ($this->staffMember)('cashier', ['orders.take', 'payments.take', 'receipts.reprint']);
    app(TillSession::class)->signIn($this->cashier);
});

function sellChaiForCash(int $shillingsGiven): Order
{
    Livewire::test(Sale::class)
        ->call('add', PRINT_CHAI)
        ->call('add', PRINT_CHAI)
        ->call('startPayment')
        ->call('quickCash', $shillingsGiven)
        ->call('complete')
        ->assertSet('step', Sale::DONE);

    return Order::query()->sole();
}

it('prints the receipt after a cash sale and opens the drawer', function () {
    (new PrinterSettings(PrinterSettings::WINDOWS, 'POS-80', 80))->save();

    sellChaiForCash(200);

    $job = RecordingPrinterConnections::$jobs[0] ?? '';

    expect(RecordingPrinterConnections::$jobs)->toHaveCount(1)
        ->and($job)->toContain('Nyali')
        ->toContain(ReceiptPrinter::row('Receipt No. T1-000001', '26/10/2026 14:05', 48))
        ->toContain('Served by Cashier Njeri')
        ->toContain('2 x Chai ya tangawizi')
        // VAT is inside the price: the amount before VAT and the VAT add up to the total.
        ->toContain(ReceiptPrinter::row('Total excl. VAT', '87.07', 48))
        ->toContain(ReceiptPrinter::row('VAT 16% on 87.07', '13.93', 48))
        ->toContain(ReceiptPrinter::row('TOTAL', 'KES 101.00', 48))
        ->not->toContain('REPRINT')
        ->toContain('Change')
        ->toContain('99.00')
        ->toContain(DRAWER_PULSE)
        ->toContain(PAPER_CUT);
});

it('lines amounts up to the right edge of 80 mm paper, wrapping long names', function () {
    expect(ReceiptPrinter::row('2 x Chai', '101.00', 48))->toBe(str_pad('2 x Chai', 41).' 101.00'."\n")
        ->and(ReceiptPrinter::row(str_repeat('N', 50), '1.00', 48))->toBe(str_repeat('N', 43)."\n".str_pad('NNNNNNN', 43).' 1.00'."\n")
        ->and(ReceiptPrinter::money(123_456_78))->toBe('123,456.78')
        ->and(ReceiptPrinter::ascii('Wanjiku’s café × 2'))->toBe("Wanjiku's cafe x 2");
});

it('keeps the sale when the printer is unplugged, and says so on the screen', function () {
    (new PrinterSettings(PrinterSettings::DEVICE, '/dev/usb/lp0', 80))->save();
    RecordingPrinterConnections::$unplugged = true;

    $screen = Livewire::test(Sale::class)
        ->call('add', PRINT_CHAI)
        ->call('startPayment')
        ->call('quickCash', 100)
        ->call('complete')
        ->assertSet('step', Sale::DONE);

    expect(Order::query()->count())->toBe(1)
        ->and(SyncState::read(PrintReceiptJob::LAST_ERROR))->toBe('Printer not found');

    $screen->call('$refresh')->assertSee(__('terminal.sale.done.print_failed', ['reason' => 'Printer not found']));
});

it('prints on its own queue, so a receipt never waits behind a sync', function () {
    Queue::fake();

    sellChaiForCash(200);
    PrintBillJob::dispatch((string) Str::uuid7());

    Queue::assertPushedOn(PrintReceiptJob::QUEUE, PrintReceiptJob::class);
    Queue::assertPushedOn(PrintReceiptJob::QUEUE, PrintBillJob::class);
    Queue::assertNotPushed(PrintReceiptJob::class, fn (PrintReceiptJob $job) => $job->queue !== PrintReceiptJob::QUEUE);
    expect(config('nativephp.queue_workers.printing.queues'))->toBe([PrintReceiptJob::QUEUE]);
});

it('prints nothing when no printer is set up', function () {
    sellChaiForCash(200);

    expect(RecordingPrinterConnections::$jobs)->toBe([]);
});

it('reprints without opening the drawer, marked as a reprint and kept for the owner', function () {
    (new PrinterSettings(PrinterSettings::WINDOWS, 'POS-80', 80))->save();

    Livewire::test(Sale::class)
        ->call('add', PRINT_CHAI)
        ->call('startPayment')
        ->call('quickCash', 100)
        ->call('complete')
        ->assertSee(__('terminal.sale.done.title', ['number' => 'T1-000001']))
        ->call('reprint');

    $reprint = ReceiptReprint::query()->sole();

    expect(RecordingPrinterConnections::$jobs)->toHaveCount(2)
        ->and(RecordingPrinterConnections::$jobs[1])->not->toContain(DRAWER_PULSE)->toContain('*** REPRINT ***')->toContain('Receipt No. T1-000001')
        ->and($reprint->order_id)->toBe(Order::query()->sole()->id)
        ->and($reprint->staff_id)->toBe($this->cashier->id)
        ->and($reprint->sync_status)->toBe('pending');
});

it('lets only staff allowed to reprint do it, and records nothing otherwise', function () {
    (new PrinterSettings(PrinterSettings::WINDOWS, 'POS-80', 80))->save();
    app(TillSession::class)->signIn(($this->staffMember)('cashier', ['orders.take', 'payments.take']));

    Livewire::test(Sale::class)
        ->call('add', PRINT_CHAI)
        ->call('startPayment')
        ->call('quickCash', 100)
        ->call('complete')
        ->call('reprint');

    expect(RecordingPrinterConnections::$jobs)->toHaveCount(1)
        ->and(ReceiptReprint::query()->count())->toBe(0);
});

it('shows each VAT rate a sale has, with zero-rated and exempt lines only when sold', function () {
    (new PrinterSettings(PrinterSettings::WINDOWS, 'POS-80', 80))->save();
    Item::query()->insert([
        ['id' => PRINT_WATER, 'category_id' => null, 'type' => 'product', 'name' => 'Water', 'sku' => null, 'barcode' => null, 'price_cents' => 5_000, 'vat_category' => 'zero_rated', 'is_active' => true],
        ['id' => PRINT_BREAD, 'category_id' => null, 'type' => 'product', 'name' => 'Bread', 'sku' => null, 'barcode' => null, 'price_cents' => 6_000, 'vat_category' => 'exempt', 'is_active' => true],
    ]);

    Livewire::test(Sale::class)
        ->call('add', PRINT_CHAI)->call('add', PRINT_WATER)->call('add', PRINT_BREAD)
        ->call('startPayment')
        ->call('quickCash', 200)
        ->call('complete');

    // Chai 50.50 carries 6.97 VAT; water and bread carry none.
    expect(RecordingPrinterConnections::$jobs[0])
        ->toContain(ReceiptPrinter::row('Total excl. VAT', '153.53', 48))
        ->toContain(ReceiptPrinter::row('VAT 16% on 43.53', '6.97', 48))
        ->toContain(ReceiptPrinter::row('VAT 0% on 50.00', '0.00', 48))
        ->toContain(ReceiptPrinter::row('VAT exempt on 60.00', '0.00', 48))
        ->toContain(ReceiptPrinter::row('TOTAL', 'KES 160.50', 48));
});

it('falls back to the sale number until the till knows its code', function () {
    (new PrinterSettings(PrinterSettings::WINDOWS, 'POS-80', 80))->save();
    app(Till::class)->identity()?->update(['till_code' => null]);

    $order = sellChaiForCash(200);

    expect($order->receipt_number)->toBeNull()
        ->and(RecordingPrinterConnections::$jobs[0])->toContain('Sale 1');
});

it('lets managers set up the printer and print a test page, and keeps cashiers out', function () {
    Livewire::test(PrinterSetup::class)->assertForbidden();

    app(TillSession::class)->signIn(($this->staffMember)('manager', ['orders.take', 'payments.take']));

    Livewire::test(PrinterSetup::class)
        ->set('connection', PrinterSettings::NETWORK)
        ->set('target', '192.168.1.50')
        ->set('paperMm', 80)
        ->call('testPrint')
        ->assertSee(__('terminal.printer.test_sent'));

    expect(PrinterSettings::current())->connection->toBe(PrinterSettings::NETWORK)->target->toBe('192.168.1.50')
        ->and(RecordingPrinterConnections::$jobs[0] ?? '')->toContain('Printer test')->toContain(PAPER_CUT);
});

it('prints a VOID slip with what to give back, why and who approved it, and opens the drawer for cash', function () {
    (new PrinterSettings(PrinterSettings::WINDOWS, 'POS-80', 80))->save();
    $slip = fn (int $cash, int $mpesa) => new VoidedSale(
        receiptNumber: 'T1-000004',
        placedAt: CarbonImmutable::parse('2026-10-26T10:30:00Z'),
        voidedAt: CarbonImmutable::parse('2026-10-26T11:05:00Z'),
        lines: [['name' => 'Chai ya tangawizi', 'quantity' => 2, 'line_total_cents' => 10_100]],
        totalCents: 10_100,
        cashCents: $cash,
        mpesaCents: $mpesa,
        reason: 'other',
        note: 'Customer walked out',
        voidedBy: 'Cashier Njeri',
        approvedBy: 'Manager Baraka',
    );

    (new PrintVoidSlipJob($slip(10_100, 0)))->handle(app(ReceiptPrinter::class));
    (new PrintVoidSlipJob($slip(0, 10_100)))->handle(app(ReceiptPrinter::class));

    expect(RecordingPrinterConnections::$jobs[0])
        ->toContain('*** VOID ***')
        ->toContain(ReceiptPrinter::row('Receipt No. T1-000004', '26/10/2026 13:30', 48))
        ->toContain(ReceiptPrinter::row('Voided', '26/10/2026 14:05', 48))
        ->toContain(ReceiptPrinter::row('2 x Chai ya tangawizi', '101.00', 48))
        ->toContain(ReceiptPrinter::row('CASH GIVEN BACK', 'KES 101.00', 48))
        ->toContain('Reason: Other')
        ->toContain('Note: Customer walked out')
        ->toContain('Voided by: Cashier Njeri')
        ->toContain('Approved by: Manager Baraka')
        ->toContain(DRAWER_PULSE)
        ->and(RecordingPrinterConnections::$jobs[1])
        ->toContain('M-Pesa KES 101.00: the owner will refund it.')
        ->not->toContain('CASH GIVEN BACK')
        ->not->toContain(DRAWER_PULSE);
});

it('wraps long names and notes between words, keeping the amount on the last line', function () {
    expect(ReceiptPrinter::row('3 x Mandazi (plain, freshly made this morning)', '60.00', 48))
        ->toBe("3 x Mandazi (plain, freshly made this\n".str_pad('morning)', 42).' 60.00'."\n")
        ->and(ReceiptPrinter::wrap('Takeaway: Mama Achieng wa Kongowea', 24))->toBe("Takeaway: Mama Achieng\nwa Kongowea\n")
        ->and(ReceiptPrinter::wrap(str_repeat('x', 30), 24))->toBe(str_repeat('x', 24)."\n".str_repeat('x', 6)."\n");
});
