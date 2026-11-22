<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SyncState;
use App\Printing\PrinterSettings;
use App\Printing\PrintFailed;
use App\Printing\ReceiptPrinter;
use App\Tickets\VoidedTicketLine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Prints the VOID slip for the kitchen or bar after a sent line comes off a
 * ticket. No drawer: no money moves. Not sent yet: it waits for kitchen
 * printing, so orders and their voids print in the kitchen together (Daniel, T6).
 */
final class PrintTicketVoidSlipJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly VoidedTicketLine $line)
    {
        $this->onQueue(PrintReceiptJob::QUEUE);
    }

    public function handle(ReceiptPrinter $printer): void
    {
        if (! PrinterSettings::current()->isSet()) {
            return;
        }

        try {
            $printer->ticketVoidSlip($this->line);
            SyncState::write(PrintReceiptJob::LAST_ERROR, null);
        } catch (PrintFailed $e) {
            SyncState::write(PrintReceiptJob::LAST_ERROR, $e->getMessage());
            Log::warning('till.print_failed', ['ticket_void_of' => $this->line->ticketId, 'error' => $e->getMessage()]);
        }
    }
}
