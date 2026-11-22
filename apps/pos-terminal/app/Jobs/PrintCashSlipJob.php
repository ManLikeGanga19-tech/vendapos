<?php

declare(strict_types=1);

namespace App\Jobs;

use App\CashUp\ShiftReport;
use App\Models\CashMovement;
use App\Models\SyncState;
use App\Printing\PrinterSettings;
use App\Printing\PrintFailed;
use App\Printing\ReceiptPrinter;
use App\Till\Till;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Prints the slip for a pay-out or drop and opens the drawer, after it is saved. */
final class PrintCashSlipJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $movementId)
    {
        $this->onQueue(PrintReceiptJob::QUEUE);
    }

    public function handle(ReceiptPrinter $printer, Till $till): void
    {
        $movement = CashMovement::query()->with('shift')->find($this->movementId);

        if ($movement?->shift === null || ! PrinterSettings::current()->isSet()) {
            return;
        }

        try {
            $printer->cashSlip($movement, ShiftReport::label($till->identity()?->till_code, $movement->shift->number));
            SyncState::write(PrintReceiptJob::LAST_ERROR, null);
        } catch (PrintFailed $e) {
            SyncState::write(PrintReceiptJob::LAST_ERROR, $e->getMessage());
            Log::warning('till.print_failed', ['cash_movement' => $this->movementId, 'error' => $e->getMessage()]);
        }
    }
}
