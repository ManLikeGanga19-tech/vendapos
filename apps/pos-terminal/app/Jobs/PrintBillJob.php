<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SyncState;
use App\Models\Ticket;
use App\Printing\PrinterSettings;
use App\Printing\PrintFailed;
use App\Printing\ReceiptPrinter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Prints a ticket's bill for the guest to check before paying. A failure is
 * kept for the screen to show, as for receipts; the bill can be printed again.
 */
final class PrintBillJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $ticketId) {}

    public function handle(ReceiptPrinter $printer): void
    {
        $ticket = Ticket::query()->find($this->ticketId);

        if ($ticket === null || ! PrinterSettings::current()->isSet()) {
            return;
        }

        try {
            $printer->bill($ticket);
            SyncState::write(PrintReceiptJob::LAST_ERROR, null);
        } catch (PrintFailed $e) {
            SyncState::write(PrintReceiptJob::LAST_ERROR, $e->getMessage());
            Log::warning('till.print_failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }
    }
}
