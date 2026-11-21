<?php

declare(strict_types=1);

use App\CashUp\Count;
use App\CashUp\OpenShift;
use App\Jobs\PrintCashSlipJob;
use App\Jobs\PrintShiftReportJob;
use App\Livewire\CashUp;
use App\Livewire\Sale;
use App\Models\CashMovement;
use App\Models\Item;
use App\Models\Shift;
use App\Models\StaffMember;
use App\Printing\PrinterConnections;
use App\Printing\PrinterSettings;
use App\Sales\RecordSale;
use App\Security\SecretBox;
use App\Staff\TillSession;
use App\Sync\SyncStaff;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\RecordingPrinterConnections;
use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Enums\CashMovementKind;

const DRAWER_SODA = '0199b1f2-0000-7000-8000-00000000c701';
const DRAWER_MANAGER_PIN = '913572';

beforeEach(function () {
    $this->travelTo('2026-11-22T06:00:00Z'); // 09:00 in Nairobi
    pairTill();
    Item::query()->insert(['id' => DRAWER_SODA, 'category_id' => null, 'type' => 'product', 'name' => 'Soda', 'sku' => null, 'barcode' => null, 'price_cents' => 8_000, 'vat_category' => 'standard', 'is_active' => true]);

    $member = fn (string $id, string $name, string $role, array $permissions, string $pinHash, int $pinLength) => StaffMember::query()->create(SyncStaff::row(
        ['id' => $id, 'name' => $name, 'role' => $role, 'permissions' => $permissions, 'pin_hash' => $pinHash, 'pin_length' => $pinLength],
        app(SecretBox::class),
    ));
    $this->cashier = $member('0199b1f2-0000-7000-8000-00000000c801', 'Achieng', 'cashier', ['orders.take', 'payments.take'], '$2y$04$hash', 4);
    $this->manager = $member('0199b1f2-0000-7000-8000-00000000c802', 'Baraka', 'manager', ['orders.take', 'payments.take', 'orders.void', 'shifts.close'], password_hash(DRAWER_MANAGER_PIN, PASSWORD_BCRYPT, ['cost' => 4]), 6);
    app(TillSession::class)->signIn($this->cashier);
});

/** A shift with a KES 5,000 float and one KES 80 cash sale: KES 5,080 expected. */
function drawerWithSale(): Shift
{
    $shift = app(OpenShift::class)->handle(test()->cashier, Count::of([1000 => 5]));
    app(RecordSale::class)->handle(test()->cashier, [['item_id' => DRAWER_SODA, 'quantity' => 1]], [
        TenderData::from(['method' => 'cash', 'amount_cents' => 8_000, 'tendered_cents' => 8_000, 'change_cents' => 0]),
    ]);

    return $shift;
}

function managerPin(Testable $screen): Testable
{
    foreach (str_split(DRAWER_MANAGER_PIN) as $digit) {
        $screen->call('press', $digit);
    }

    return $screen;
}

describe('screen', function () {
    beforeEach(fn () => Queue::fake());

    it('keeps Pay shut on the sale screen until a shift is open', function () {
        Livewire::test(Sale::class)
            ->assertSee(__('terminal.cash_up.open_to_sell'))
            ->call('add', DRAWER_SODA)
            ->call('startPayment')
            ->assertSet('step', Sale::CART)
            ->assertSet('message', __('terminal.sale.refused.no_shift'));
    });

    it('opens a shift by counting the float note by note, then goes back to selling', function () {
        Livewire::test(CashUp::class)
            ->assertSet('step', CashUp::OPEN)
            ->set('counts.1000', '5')
            ->set('counts.50', '2')
            ->assertSee('KES 5,100.00')
            ->call('openShift')
            ->assertRedirect(route('home'));

        expect(Shift::query()->sole()->openingFloat()->total()->cents)->toBe(510_000);

        Livewire::test(Sale::class)->assertDontSee(__('terminal.cash_up.open_to_sell'));
    });

    it('counts blind, shows the difference after, and allows one recount before a manager approves', function () {
        $shift = drawerWithSale();

        $screen = Livewire::test(CashUp::class)
            ->assertSet('step', CashUp::SHIFT)
            ->call('startClose')
            ->assertSet('step', CashUp::COUNT)
            ->assertDontSee('5,080.00')
            ->set('counts.1000', '4')
            ->call('countDone')
            ->assertSet('step', CashUp::DIFFERENCE)
            ->assertSee(__('terminal.cash_up.difference.short'))
            ->assertSee('1,080.00')
            ->assertSee(__('terminal.cash_up.recount'))
            ->call('back')
            ->assertSet('step', CashUp::DIFFERENCE)
            ->call('recount')
            ->assertSet('step', CashUp::COUNT)
            ->assertSet('counts.1000', '')
            ->set('counts.1000', '4')
            ->call('countDone')
            ->assertSet('step', CashUp::DIFFERENCE)
            ->assertDontSee(__('terminal.cash_up.recount'))
            ->call('recount')
            ->assertSet('step', CashUp::DIFFERENCE)
            ->call('askApproval')
            ->call('confirmNote')
            ->assertHasErrors('note')
            ->set('note', 'KES 1,000 given as change for a 500')
            ->call('confirmNote')
            ->assertSet('step', CashUp::APPROVER)
            ->call('chooseApprover', $this->cashier->id)
            ->assertSet('step', CashUp::APPROVER)
            ->call('chooseApprover', $this->manager->id)
            ->assertSet('step', CashUp::PIN);

        managerPin($screen)->assertSet('step', CashUp::CLOSED)->assertSee('Z REPORT');

        expect($shift->fresh()?->only(['closed_by', 'approved_by', 'note', 'expected_cents']))->toBe([
            'closed_by' => $this->cashier->id, 'approved_by' => $this->manager->id, 'note' => 'KES 1,000 given as change for a 500', 'expected_cents' => 508_000,
        ]);
        Queue::assertPushed(PrintShiftReportJob::class, fn (PrintShiftReportJob $job) => $job->shiftId === $shift->id);
    });

    it('closes there and then when the count is within the allowance, and prints the Z report', function () {
        $shift = drawerWithSale();

        Livewire::test(CashUp::class)
            ->call('startClose')
            ->set('counts.1000', '5')
            ->set('counts.50', '1')
            ->call('countDone')
            ->assertSet('step', CashUp::CLOSED)
            ->assertSee(__('terminal.shift_report.short'))
            ->assertSee('30.00')
            ->call('openNext')
            ->assertSet('step', CashUp::OPEN);

        expect($shift->fresh()?->approved_by)->toBeNull()
            ->and($shift->fresh()?->isClosed())->toBeTrue();
        Queue::assertPushed(PrintShiftReportJob::class);
    });

    it('takes a pay-out with a manager’s PIN and prints its slip', function () {
        drawerWithSale();

        $screen = Livewire::test(CashUp::class)
            ->call('startOut', 'pay_out')
            ->set('amount', '50')
            ->call('confirmOut')
            ->assertHasErrors('reason')
            ->set('reason', 'Charcoal')
            ->set('amount', '6000')
            ->call('confirmOut')
            ->assertSet('message', __('terminal.cash_up.refused.more_than_drawer'))
            ->set('amount', '50')
            ->call('confirmOut')
            ->call('chooseApprover', $this->manager->id);

        managerPin($screen)
            ->assertSet('step', CashUp::SHIFT)
            ->assertSee(__('terminal.cash_up.out_done.pay_out', ['amount' => 'KES 50.00']));

        $movement = CashMovement::query()->sole();
        expect($movement->only(['kind', 'amount_cents', 'reason', 'approved_by']))->toBe(['kind' => CashMovementKind::PayOut, 'amount_cents' => 5_000, 'reason' => 'Charcoal', 'approved_by' => $this->manager->id]);
        Queue::assertPushed(PrintCashSlipJob::class, fn (PrintCashSlipJob $job) => $job->movementId === $movement->id);
    });

    it('shows the X report, with the expected cash, only to someone who may close shifts', function () {
        drawerWithSale();

        Livewire::test(CashUp::class)
            ->assertDontSee(__('terminal.cash_up.x_report'))
            ->call('showX')
            ->assertSet('step', CashUp::SHIFT)
            ->assertDontSee('5,080.00');

        app(TillSession::class)->signIn($this->manager);

        Livewire::test(CashUp::class)
            ->call('showX')
            ->assertSet('step', CashUp::X)
            ->assertSee('X REPORT')
            ->assertSee('5,080.00');
    });

    it('is not for someone who cannot take payments', function () {
        $waiter = StaffMember::query()->create(SyncStaff::row(
            ['id' => '0199b1f2-0000-7000-8000-00000000c803', 'name' => 'Otieno', 'role' => 'waiter', 'permissions' => ['orders.take'], 'pin_hash' => '$2y$04$hash', 'pin_length' => 4],
            app(SecretBox::class),
        ));
        app(TillSession::class)->signIn($waiter);

        $this->get(route('cash-up'))->assertForbidden();
    });
});

describe('printing', function () {
    beforeEach(function () {
        RecordingPrinterConnections::$jobs = [];
        RecordingPrinterConnections::$unplugged = false;
        $this->app->bind(PrinterConnections::class, RecordingPrinterConnections::class);
        (new PrinterSettings(PrinterSettings::WINDOWS, 'POS-80', 80))->save();
    });

    it('prints a pay-out slip with who took it and who approved it, and opens the drawer', function () {
        Queue::fake();
        drawerWithSale();
        $screen = Livewire::test(CashUp::class)->call('startOut', 'pay_out')->set('amount', '50')->set('reason', 'Charcoal')->call('confirmOut')->call('chooseApprover', $this->manager->id);
        managerPin($screen);

        app()->call([new PrintCashSlipJob(CashMovement::query()->sole()->id), 'handle']); // the queue is faked: run it here
        $job = RecordingPrinterConnections::$jobs[0] ?? '';

        expect($job)->toContain('*** PAY-OUT ***')
            ->toContain('T1-Z0001')
            ->toContain('KES 50.00')
            ->toContain('For: Charcoal')
            ->toContain('Taken by: Achieng')
            ->toContain('Approved by: Baraka')
            ->toContain("\x1bp");
    });

    it('prints the Z report with the working, the count and the difference, and leaves the drawer shut', function () {
        Queue::fake();
        $shift = drawerWithSale();
        Livewire::test(CashUp::class)->call('startClose')->set('counts.1000', '5')->set('counts.50', '1')->call('countDone');

        app()->call([new PrintShiftReportJob($shift->id), 'handle']); // the queue is faked: run it here
        $job = RecordingPrinterConnections::$jobs[0] ?? '';

        expect($job)->toContain('Z REPORT')
            ->toContain('T1-Z0001')
            ->toContain('Float')
            ->toContain('5,000.00')
            ->toContain('Expected in drawer')
            ->toContain('5,080.00')
            ->toContain('5,050.00')
            ->toContain('Short')
            ->toContain('30.00')
            ->toContain('5 x 1000')
            ->not->toContain("\x1bp");
    });
});
