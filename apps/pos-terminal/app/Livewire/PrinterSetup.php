<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\FollowsTillLanguage;
use App\Livewire\Concerns\NeedsSignedInStaff;
use App\Printing\PrinterSettings;
use App\Printing\PrintFailed;
use App\Printing\ReceiptPrinter;
use App\Staff\TillSession;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Where a manager or owner tells the till how to reach its receipt printer
 * and checks it with a test page. Stored on this till only.
 */
final class PrinterSetup extends Component
{
    use FollowsTillLanguage;
    use NeedsSignedInStaff;

    public string $connection = PrinterSettings::NONE;

    public string $target = '';

    public int $paperMm = 80;

    public ?string $message = null;

    public bool $ok = false;

    public function mount(TillSession $session): void
    {
        abort_unless(in_array($session->staff()?->role, ['manager', 'owner'], true), 403);

        $settings = PrinterSettings::current();
        $this->connection = $settings->connection;
        $this->target = $settings->target;
        $this->paperMm = $settings->paperMm;
    }

    public function save(TillSession $session): void
    {
        abort_unless(in_array($session->staff()?->role, ['manager', 'owner'], true), 403);

        $this->validate([
            'connection' => ['required', 'in:'.implode(',', PrinterSettings::CONNECTIONS)],
            'target' => ['nullable', 'string', 'max:120', 'required_unless:connection,'.PrinterSettings::NONE],
            'paperMm' => ['required', 'in:'.implode(',', array_keys(PrinterSettings::COLUMNS))],
        ], attributes: ['target' => __('terminal.printer.target')]);

        (new PrinterSettings($this->connection, trim($this->target), $this->paperMm))->save();
        $this->ok = true;
        $this->message = __('terminal.printer.saved');
    }

    /** Saves, then prints a test page now, so the result shows on screen. */
    public function testPrint(TillSession $session, ReceiptPrinter $printer): void
    {
        $this->save($session);

        try {
            $printer->test();
            $this->ok = true;
            $this->message = __('terminal.printer.test_sent');
        } catch (PrintFailed $e) {
            $this->ok = false;
            $this->message = __('terminal.printer.failed', ['reason' => $e->getMessage()]);
        }
    }

    public function render(): View
    {
        return view('livewire.printer-setup', ['connections' => PrinterSettings::CONNECTIONS]);
    }
}
