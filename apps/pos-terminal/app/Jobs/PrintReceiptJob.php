<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Models\SyncState;
use App\Printing\PrinterSettings;
use App\Printing\PrintFailed;
use App\Printing\ReceiptPrinter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Prints a sale's receipt (and opens the cash drawer for cash) after the sale
 * is saved, so a jammed or unplugged printer never holds up the counter. A
 * failure is kept for the screen to show; the cashier can reprint.
 */
final class PrintReceiptJob implements ShouldQueue
{
    use Queueable;

    public const string LAST_ERROR = 'printer.last_error';

    public int $tries = 1;

    public function __construct(
        public readonly string $orderId,
        public readonly bool $openDrawer = false,
        public readonly bool $reprint = false,
    ) {}

    public function handle(ReceiptPrinter $printer): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order === null || ! PrinterSettings::current()->isSet()) {
            return;
        }

        try {
            $printer->receipt($order, $this->openDrawer, $this->reprint);
            SyncState::write(self::LAST_ERROR, null);
        } catch (PrintFailed $e) {
            SyncState::write(self::LAST_ERROR, $e->getMessage());
            Log::warning('till.print_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
