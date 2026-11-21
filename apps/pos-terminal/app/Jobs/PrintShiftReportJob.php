<?php

declare(strict_types=1);

namespace App\Jobs;

use App\CashUp\Drawer;
use App\CashUp\ShiftReport;
use App\Models\Shift;
use App\Models\SyncState;
use App\Printing\PrinterSettings;
use App\Printing\PrintFailed;
use App\Printing\ReceiptPrinter;
use App\Till\Till;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Prints a shift's X report while it is open, or its Z report once closed. Never opens the drawer. */
final class PrintShiftReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $shiftId)
    {
        $this->onQueue(PrintReceiptJob::QUEUE);
    }

    public function handle(ReceiptPrinter $printer, Drawer $drawer, Till $till): void
    {
        $shift = Shift::query()->find($this->shiftId);

        if ($shift === null || ! PrinterSettings::current()->isSet()) {
            return;
        }

        try {
            $printer->shiftReport(ShiftReport::for($shift, $drawer, $till));
            SyncState::write(PrintReceiptJob::LAST_ERROR, null);
        } catch (PrintFailed $e) {
            SyncState::write(PrintReceiptJob::LAST_ERROR, $e->getMessage());
            Log::warning('till.print_failed', ['shift' => $this->shiftId, 'error' => $e->getMessage()]);
        }
    }
}
