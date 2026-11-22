<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SyncState;
use App\Printing\PrinterSettings;
use App\Printing\PrintFailed;
use App\Printing\ReceiptPrinter;
use App\Sales\VoidedSale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Prints the VOID slip (and opens the drawer when cash goes back) after the
 * void is saved. Carries the slip's contents: a sale of another till is not
 * in this till's database.
 */
final class PrintVoidSlipJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly VoidedSale $sale)
    {
        $this->onQueue(PrintReceiptJob::QUEUE);
    }

    public function handle(ReceiptPrinter $printer): void
    {
        if (! PrinterSettings::current()->isSet()) {
            return;
        }

        try {
            $printer->voidSlip($this->sale, openDrawer: $this->sale->cashCents > 0);
            SyncState::write(PrintReceiptJob::LAST_ERROR, null);
        } catch (PrintFailed $e) {
            SyncState::write(PrintReceiptJob::LAST_ERROR, $e->getMessage());
            Log::warning('till.print_failed', ['void_of' => $this->sale->receiptNumber, 'error' => $e->getMessage()]);
        }
    }
}
