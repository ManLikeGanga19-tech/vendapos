<?php

declare(strict_types=1);

use App\Jobs\PrintReceiptJob;
use App\Livewire\PrinterSetup;
use App\Livewire\Sale;
use App\Models\Item;
use App\Models\Order;
use App\Models\StaffMember;
use App\Models\SyncState;
use App\Printing\PrinterConnections;
use App\Printing\PrinterSettings;
use App\Printing\ReceiptPrinter;
use App\Security\SecretBox;
use App\Staff\TillSession;
use App\Sync\SyncStaff;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\RecordingPrinterConnections;

const PRINT_CHAI = '0199b1f2-0000-7000-8000-00000000e001';

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
        ->toContain('Sale 1   26/10/2026 14:05')
        ->toContain('Served by Cashier Njeri')
        ->toContain('2 x Chai ya tangawizi')
        ->toContain('101.00')
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

it('prints nothing when no printer is set up', function () {
    sellChaiForCash(200);

    expect(RecordingPrinterConnections::$jobs)->toBe([]);
});

it('reprints without opening the drawer', function () {
    (new PrinterSettings(PrinterSettings::WINDOWS, 'POS-80', 80))->save();

    Livewire::test(Sale::class)
        ->call('add', PRINT_CHAI)
        ->call('startPayment')
        ->call('quickCash', 100)
        ->call('complete')
        ->call('reprint');

    expect(RecordingPrinterConnections::$jobs)->toHaveCount(2)
        ->and(RecordingPrinterConnections::$jobs[1])->not->toContain(DRAWER_PULSE);
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
