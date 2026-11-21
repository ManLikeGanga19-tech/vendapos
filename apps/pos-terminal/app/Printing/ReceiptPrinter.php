<?php

declare(strict_types=1);

namespace App\Printing;

use App\Api\Data\BillQr;
use App\Livewire\StatusBar;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderTender;
use App\Models\StaffMember;
use App\Models\Ticket;
use App\Sales\VoidedSale;
use App\Tickets\VoidedTicketLine;
use App\Till\Till;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Throwable;
use Transliterator;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\Tax\VatBreakdown;

/**
 * Prints on the till's ESC/POS receipt printer. Text stays plain ASCII,
 * which every generic thermal printer's built-in code page can show. The
 * fiscal (eTIMS) part of the receipt is added when eTIMS is switched on.
 */
class ReceiptPrinter
{
    public function __construct(
        private readonly PrinterConnections $connections,
        private readonly Till $till,
    ) {}

    /**
     * A reprint says so at the top, so a copy can't pass as a second sale.
     *
     * @throws PrintFailed
     */
    public function receipt(Order $order, bool $openDrawer = false, bool $reprint = false): void
    {
        $order->loadMissing(['lines', 'tenders']);

        $this->print(function (Printer $printer, int $columns) use ($order, $openDrawer, $reprint) {
            $this->header($printer);

            if ($reprint) {
                $printer->setEmphasis(true);
                $printer->text(self::ascii(__('terminal.receipt.reprint'))."\n");
                $printer->setEmphasis(false);
            }

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(self::row(
                $order->receipt_number !== null
                    ? __('terminal.receipt.number', ['number' => $order->receipt_number])
                    : __('terminal.receipt.sale', ['number' => $order->sequence_number]),
                $order->placed_at->setTimezone(StatusBar::TIMEZONE)->format('d/m/Y H:i'),
                $columns,
            ));
            $staff = StaffMember::query()->find($order->staff_id);

            if ($staff !== null) {
                $printer->text(self::ascii(__('terminal.receipt.served_by', ['name' => $staff->name]))."\n");
            }

            if ($order->ticket_id !== null) {
                // The same words as on the bill: "Table 4", or the takeaway and its name.
                $ticket = new Ticket(['table_name' => $order->table_name, 'label' => $order->ticket_label]);
                $printer->text(self::ascii($ticket->title())."\n");
            }

            $printer->text(str_repeat('-', $columns)."\n");

            foreach ($order->lines as $line) {
                /** @var OrderLine $line */
                $printer->text(self::row("{$line->quantity} x {$line->name}", self::money($line->line_total_cents), $columns));
            }

            $printer->text(str_repeat('-', $columns)."\n");
            $this->vatAndTotal(
                $printer,
                array_values($order->lines->map(fn (OrderLine $line) => $line->toOrderLine())->all()),
                __('terminal.receipt.total'),
                $order->total_cents,
                $columns,
            );

            foreach ($order->tenders as $tender) {
                /** @var OrderTender $tender */
                match ($tender->method) {
                    'cash' => $printer->text(
                        self::row(__('terminal.receipt.cash'), self::money((int) $tender->tendered_cents), $columns)
                        .self::row(__('terminal.receipt.change'), self::money((int) $tender->change_cents), $columns),
                    ),
                    default => $printer->text(self::row('M-Pesa '.($tender->mpesa_reference ?? ''), self::money($tender->amount_cents), $columns)),
                };
            }

            $printer->text(str_repeat('-', $columns)."\n");
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text(self::ascii(__('terminal.receipt.thanks'))."\n");

            if ($openDrawer) {
                $printer->pulse();
            }
        });
    }

    /**
     * The guest's bill before paying. Not a tax invoice: no KRA PIN, QR code
     * or eTIMS details; the receipt after payment is the fiscal document.
     *
     * @throws PrintFailed
     */
    public function bill(Ticket $ticket, ?BillQr $qr = null): void
    {
        $ticket->loadMissing('lines');

        $this->print(function (Printer $printer, int $columns) use ($ticket, $qr) {
            $this->header($printer);

            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text(self::ascii(__('terminal.bill.title'))."\n");
            $printer->selectPrintMode();
            $printer->text(self::ascii(__('terminal.bill.not_invoice'))."\n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(self::ascii($ticket->title().'   '.now()->setTimezone(StatusBar::TIMEZONE)->format('d/m/Y H:i'))."\n");
            $staff = StaffMember::query()->find($ticket->staff_id);

            if ($staff !== null) {
                $printer->text(self::ascii(__('terminal.receipt.served_by', ['name' => $staff->name]))."\n");
            }

            $printer->text(str_repeat('-', $columns)."\n");

            // What is left of each line once voids are taken off.
            foreach ($ticket->billedLines() as $line) {
                $orderLine = $line->toOrderLine();
                $printer->text(self::row("{$orderLine->quantity} x {$line->name}", self::money($orderLine->line_total_cents), $columns));
            }

            $printer->text(str_repeat('-', $columns)."\n");
            $this->vatAndTotal(
                $printer,
                array_map(fn ($line) => $line->toOrderLine(), $ticket->billedLines()),
                __('terminal.bill.to_pay'),
                $ticket->totals()->total->cents,
                $columns,
            );

            if ($qr !== null) {
                $this->scanToPay($printer, $qr, $columns);
            }
            $printer->text(str_repeat('-', $columns)."\n");
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text(self::ascii(__('terminal.bill.footer'))."\n");
        });
    }

    /**
     * Proof of a void for the drawer and the owner: the sale it cancels, what
     * to give back, why, and whose PIN approved it. Opens the drawer when
     * cash goes back.
     *
     * @throws PrintFailed
     */
    public function voidSlip(VoidedSale $sale, bool $openDrawer = false): void
    {
        $this->print(function (Printer $printer, int $columns) use ($sale, $openDrawer) {
            $this->header($printer);

            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
            $printer->text(self::ascii(__('terminal.void_slip.title'))."\n");
            $printer->selectPrintMode();

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(self::row(
                __('terminal.receipt.number', ['number' => $sale->receiptNumber ?? '-']),
                $sale->placedAt->setTimezone(StatusBar::TIMEZONE)->format('d/m/Y H:i'),
                $columns,
            ));
            $printer->text(self::row(__('terminal.void_slip.voided'), $sale->voidedAt->setTimezone(StatusBar::TIMEZONE)->format('d/m/Y H:i'), $columns));
            $printer->text(str_repeat('-', $columns)."\n");

            foreach ($sale->lines as $line) {
                $printer->text(self::row("{$line['quantity']} x {$line['name']}", self::money($line['line_total_cents']), $columns));
            }

            $printer->text(str_repeat('-', $columns)."\n");
            $printer->setEmphasis(true);
            $printer->text(self::row(__('terminal.void_slip.total'), 'KES '.self::money($sale->totalCents), $columns));

            if ($sale->cashCents > 0) {
                $printer->text(self::row(__('terminal.void_slip.cash_back'), 'KES '.self::money($sale->cashCents), $columns));
            }

            $printer->setEmphasis(false);

            if ($sale->mpesaCents > 0) {
                $printer->text(self::ascii(__('terminal.void_slip.mpesa_back', ['amount' => 'KES '.self::money($sale->mpesaCents)]))."\n");
            }

            $printer->text(str_repeat('-', $columns)."\n");
            $printer->text(self::ascii(__('terminal.void_slip.reason', ['reason' => __("terminal.void_reasons.{$sale->reason}")]))."\n");

            if ($sale->note !== null) {
                $printer->text(self::ascii(__('terminal.void_slip.note', ['note' => $sale->note]))."\n");
            }

            $printer->text(self::ascii(__('terminal.void_slip.voided_by', ['name' => $sale->voidedBy]))."\n");
            $printer->text(self::ascii(__('terminal.void_slip.approved_by', ['name' => $sale->approvedBy]))."\n");

            if ($openDrawer) {
                $printer->pulse();
            }
        });
    }

    /**
     * The VOID slip for the kitchen or bar: what came off which ticket, how
     * many are still to make, why, and who approved it.
     *
     * @throws PrintFailed
     */
    public function ticketVoidSlip(VoidedTicketLine $line): void
    {
        $this->print(function (Printer $printer, int $columns) use ($line) {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
            $printer->text(self::ascii(__('terminal.void_slip.title'))."\n");
            $printer->text(self::ascii($line->ticketTitle)."\n");
            $printer->selectPrintMode();

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(self::row(__('terminal.void_slip.voided'), $line->voidedAt->setTimezone(StatusBar::TIMEZONE)->format('d/m/Y H:i'), $columns));
            $printer->text(str_repeat('-', $columns)."\n");

            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
            $printer->text(self::ascii(__('terminal.void_slip.line_off', ['quantity' => $line->quantity, 'name' => $line->name]))."\n");
            $printer->selectPrintMode();
            $printer->text(self::ascii(__('terminal.void_slip.line_left', ['quantity' => $line->left]))."\n");

            $printer->text(str_repeat('-', $columns)."\n");
            $printer->text(self::ascii(__('terminal.void_slip.reason', ['reason' => __("terminal.void_reasons.{$line->reason}")]))."\n");

            if ($line->note !== null) {
                $printer->text(self::ascii(__('terminal.void_slip.note', ['note' => $line->note]))."\n");
            }

            $printer->text(self::ascii(__('terminal.void_slip.voided_by', ['name' => $line->voidedBy]))."\n");
            $printer->text(self::ascii(__('terminal.void_slip.approved_by', ['name' => $line->approvedBy]))."\n");
        });
    }

    /** A short page to check the printer, the paper width and the cutter. @throws PrintFailed */
    public function test(): void
    {
        $this->print(function (Printer $printer, int $columns) {
            $this->header($printer);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(str_repeat('-', $columns)."\n");
            $printer->text(self::row(self::ascii(__('terminal.receipt.test')), 'OK', $columns));
            $printer->text(str_repeat('1234567890', intdiv($columns, 10)).substr('1234567890', 0, $columns % 10)."\n");
        });
    }

    /**
     * @param  callable(Printer, int): void  $write
     *
     * @throws PrintFailed
     */
    protected function print(callable $write): void
    {
        $settings = PrinterSettings::current();
        $printer = new Printer($this->connections->open($settings));

        try {
            $write($printer, $settings->columns());
            $printer->feed(3);
            $printer->cut();
        } catch (Throwable $e) {
            throw new PrintFailed($e->getMessage(), previous: $e);
        } finally {
            try {
                $printer->close();
            } catch (Throwable) {
                // The job either went through or already failed above.
            }
        }
    }

    private function header(Printer $printer): void
    {
        $identity = $this->till->identity();

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
        $printer->text(self::ascii((string) $identity?->business_name)."\n");
        $printer->selectPrintMode();
        $printer->text(self::ascii((string) $identity?->branch_name)."\n");
    }

    /**
     * Safaricom's QR, then the paybill and account number for customers who
     * type them. A QR that will not print leaves the numbers, which still pay.
     */
    private function scanToPay(Printer $printer, BillQr $qr, int $columns): void
    {
        if ($qr->png === null && ! $qr->canBeTyped()) {
            return;
        }

        $printer->text(str_repeat('-', $columns)."\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text(self::ascii(__('terminal.bill.scan_to_pay', ['amount' => self::money($qr->amountCents)]))."\n");
        $printer->setEmphasis(false);

        if ($qr->png !== null) {
            $this->image($printer, $qr->png);
        }

        if ($qr->canBeTyped()) {
            $printer->text(self::ascii(__('terminal.bill.pay_to', ['paybill' => $qr->paybill, 'account' => $qr->account]))."\n");
        }

        $printer->setJustification(Printer::JUSTIFY_LEFT);
    }

    /** Prints a base64 PNG (needs PHP's GD, which NativePHP's PHP has); skipped if it will not load. */
    private function image(Printer $printer, string $base64Png): void
    {
        $bytes = base64_decode($base64Png, true);

        if ($bytes === false) {
            return;
        }

        // The image library reads the format from the file's extension.
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vp-qr-'.Str::random(12).'.png';

        try {
            file_put_contents($path, $bytes);
            $printer->bitImage(EscposImage::load($path, false));
        } catch (Throwable $e) {
            Log::warning('till.qr_image_failed', ['error' => $e->getMessage()]);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Prices include VAT, so the total comes last and the lines above it show
     * what it is made of: the amount before VAT, then the VAT of each rate
     * the sale has. Zero-rated and exempt lines appear only when sold.
     *
     * @param  list<OrderLineData>  $lines
     */
    private function vatAndTotal(Printer $printer, array $lines, string $totalLabel, int $totalCents, int $columns): void
    {
        $bands = VatBreakdown::fromLines($lines);
        $vatCents = array_sum(array_map(fn ($band) => $band->vat->cents, $bands));

        $printer->text(self::row(__('terminal.receipt.net'), self::money($totalCents - $vatCents), $columns));

        foreach ($bands as $band) {
            $label = $band->category === VatCategory::Exempt
                ? __('terminal.receipt.vat_exempt', ['amount' => self::money($band->net()->cents)])
                : __('terminal.receipt.vat_rate', ['rate' => $band->category->rate() / 100, 'amount' => self::money($band->net()->cents)]);
            $printer->text(self::row($label, self::money($band->vat->cents), $columns));
        }

        $printer->setEmphasis(true);
        $printer->text(self::row($totalLabel, 'KES '.self::money($totalCents), $columns));
        $printer->setEmphasis(false);
    }

    /** "2 x Chai ............ 101.00", wrapping a long name onto its own lines. */
    public static function row(string $left, string $right, int $columns): string
    {
        $left = self::ascii($left);
        $room = $columns - strlen($right) - 1;
        $lines = str_split($left, max(1, $room));
        $last = (string) array_pop($lines);

        return implode('', array_map(fn (string $part) => $part."\n", $lines))
            .str_pad($last, $room).' '.$right."\n";
    }

    public static function money(int $cents): string
    {
        return number_format(intdiv($cents, 100)).'.'.str_pad((string) abs($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Plain ASCII: curly quotes and accents become their nearest letters. */
    public static function ascii(string $text): string
    {
        $text = strtr($text, ['’' => "'", '‘' => "'", '“' => '"', '”' => '"', '–' => '-', '—' => '-', '…' => '...', '×' => 'x', '·' => '-']);

        // Not iconv: its TRANSLIT output differs by platform (Windows gives "caf'e").
        $transliterator = class_exists(Transliterator::class) ? Transliterator::create('Latin-ASCII') : null;
        $converted = $transliterator?->transliterate($text);
        $text = is_string($converted) ? $converted : strtr($text, self::LATIN_ACCENTS);

        return preg_replace('/[^\x20-\x7E\n]/', '', $text) ?? '';
    }

    /** Fallback when intl is missing from the till's PHP. */
    private const array LATIN_ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u',
        'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
        'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
    ];
}
