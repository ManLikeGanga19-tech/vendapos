<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiRejected;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\VendaApi;
use App\Jobs\PrintReceiptJob;
use App\Livewire\Concerns\FollowsTillLanguage;
use App\Livewire\Concerns\NeedsSignedInStaff;
use App\Livewire\Concerns\PicksLanguage;
use App\Models\Category;
use App\Models\FloorArea;
use App\Models\Item;
use App\Models\ReceiptReprint;
use App\Models\StaffMember;
use App\Models\SyncState;
use App\Models\Ticket;
use App\Models\TicketLine;
use App\Printing\PrinterSettings;
use App\Sales\RecordSale;
use App\Sales\SaleRefused;
use App\Security\KeyringUnavailable;
use App\Staff\TillSession;
use App\Sync\SyncStatus;
use App\Tickets\PrintBill;
use App\Tickets\SendOrder;
use App\Tickets\TicketRefused;
use App\Till\Till;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Enums\TerminalMode;
use VendaPOS\Core\Rules\PhoneNumber;
use VendaPOS\Core\Tax\OrderTotals;
use VendaPOS\Core\ValueObjects\Money;

/**
 * The sale screen: pick items (or scan them), then take cash, an M-Pesa
 * push, or both. M-Pesa pays whole shillings and cash the rest (Daniel, T3a).
 * The sale is written to the till first and synced after.
 *
 * At table-service branches a waiter sends the order to a ticket instead, and
 * the cashier pays tickets from the branch's queue; quick sale stays (T4b).
 */
final class Sale extends Component
{
    use FollowsTillLanguage;
    use NeedsSignedInStaff;
    use PicksLanguage;

    public const string CART = 'cart';

    public const string PAY = 'pay';

    public const string MPESA = 'mpesa';

    public const string DONE = 'done';

    /** The branch's unpaid tickets (table service). */
    public const string TICKETS = 'tickets';

    /** Where a new ticket goes: a table, or takeaway. */
    public const string TABLE = 'table';

    /** How long the till waits on the customer's phone before offering to stop. */
    public const int MPESA_PATIENCE_SECONDS = 120;

    /** @var array<string, int> item ID => quantity, in the order added */
    public array $cart = [];

    public ?string $category = null;

    /** The ticket on screen: its sent lines are locked, the cart holds the next round. */
    public ?string $ticketId = null;

    /** Name for a takeaway or counter ticket, optional. */
    public string $takeawayName = '';

    /** "Sent to Table 4", shown after an order is sent. */
    public ?string $notice = null;

    public string $step = self::CART;

    /** The sale's UUID, fixed once payment starts so an M-Pesa push and the order share it. */
    public ?string $saleId = null;

    /**
     * M-Pesa taken so far: pushes that succeeded, and codes typed from the
     * customer's SMS (accepted offline, confirmed later on the server).
     *
     * @var list<array{method: string, amount_cents: int, payment_uuid: string|null, reference: string|null}>
     */
    public array $mpesaPaid = [];

    /** A code from the customer's M-Pesa SMS, for money paid straight to the paybill or till. */
    public string $typedCode = '';

    /** In whole shillings. */
    public string $codeAmount = '';

    /** @var array{uuid: string, amount_cents: int, started_at: int}|null the push being waited on */
    public ?array $waiting = null;

    /** Cash handed over, in whole shillings as typed. */
    public string $cashGiven = '';

    public string $phone = '';

    /** In whole shillings. */
    public string $mpesaAmount = '';

    public ?string $message = null;

    /** @var array{order_id: string, total_cents: int, change_cents: int, number: string}|null */
    public ?array $done = null;

    public function add(string $itemId): void
    {
        if ($this->step !== self::CART || ! Item::query()->whereKey($itemId)->where('is_active', true)->exists()) {
            return;
        }

        $this->cart[$itemId] = ($this->cart[$itemId] ?? 0) + 1;
        $this->message = null;
        $this->notice = null;
    }

    /** A barcode scanner types the code and Enter, like a keyboard. */
    public function scan(string $code): void
    {
        $item = Item::query()->where('barcode', trim($code))->where('is_active', true)->first();

        if ($item === null) {
            $this->message = __('terminal.sale.not_found', ['code' => trim($code)]);

            return;
        }

        $this->add($item->id);
    }

    public function decrement(string $itemId): void
    {
        if ($this->step !== self::CART || ! isset($this->cart[$itemId])) {
            return;
        }

        if (--$this->cart[$itemId] < 1) {
            unset($this->cart[$itemId]);
        }
    }

    public function remove(string $itemId): void
    {
        if ($this->step === self::CART) {
            unset($this->cart[$itemId]);
        }
    }

    public function clearCart(): void
    {
        if ($this->step === self::CART) {
            $this->cart = [];
            $this->message = null;
        }
    }

    public function pickCategory(?string $categoryId): void
    {
        $this->category = $categoryId;
    }

    /** The branch's unpaid tickets, to pick one to add to, bill or pay. */
    public function showTickets(): void
    {
        if (in_array($this->step, [self::CART, self::TABLE], true)) {
            $this->step = self::TICKETS;
            $this->message = null;
        }
    }

    /** Open a ticket: its sent lines show locked, and the cart becomes its next round. */
    public function openTicket(string $ticketId): void
    {
        if ($this->step !== self::TICKETS || ! Ticket::query()->unpaid()->whereKey($ticketId)->exists()) {
            return;
        }

        $this->ticketId = $ticketId;
        $this->step = self::CART;
        $this->message = null;
        $this->notice = null;
    }

    /** Back to a plain sale; the cart is kept. */
    public function closeTicket(): void
    {
        if (in_array($this->step, [self::CART, self::TICKETS, self::TABLE], true)) {
            $this->ticketId = null;
            $this->step = self::CART;
            $this->message = null;
        }
    }

    /**
     * Send the cart to the ticket on screen, or pick where a new one goes.
     * Then a fresh screen for the next table.
     */
    public function sendOrder(SendOrder $sendOrder, TillSession $session): void
    {
        if ($this->step !== self::CART || $this->cart === []) {
            return;
        }

        if ($this->ticketId === null) {
            $this->step = self::TABLE;
            $this->message = null;

            return;
        }

        $ticket = $this->openTicketModel();

        if ($ticket === null) {
            $this->ticketId = null;
            $this->message = __('terminal.tickets.paid_elsewhere');

            return;
        }

        $this->send($sendOrder, $session, $ticket);
    }

    /** A new ticket at $tableId, or takeaway when null. */
    public function sendTo(SendOrder $sendOrder, TillSession $session, ?string $tableId = null): void
    {
        if ($this->step === self::TABLE) {
            $this->send($sendOrder, $session, null, $tableId);
        }
    }

    public function printBill(PrintBill $printBill): void
    {
        $ticket = $this->openTicketModel();

        if ($this->step !== self::CART || $ticket === null) {
            return;
        }

        if ($this->cart !== []) {
            $this->message = __('terminal.tickets.send_first');

            return;
        }

        try {
            $printBill->handle($ticket);
        } catch (TicketRefused $e) {
            $this->message = __('terminal.tickets.refused.'.$e->reason);

            return;
        }

        SyncState::write(PrintReceiptJob::LAST_ERROR, null);
        $this->message = null;
        $this->notice = __('terminal.tickets.bill_printed', ['ticket' => $ticket->title()]);
    }

    public function startPayment(TillSession $session, SendOrder $sendOrder): void
    {
        $ticket = $this->openTicketModel();

        if ($this->cart === [] && $ticket === null) {
            return;
        }

        if (! $session->can(StaffMember::TAKE_PAYMENTS)) {
            $this->message = __('terminal.sale.refused.not_allowed');

            return;
        }

        // A round still in the cart goes on the ticket first, so the bill and the payment match.
        if ($ticket !== null && $this->cart !== [] && ! $this->send($sendOrder, $session, $ticket, keepTicket: true)) {
            return;
        }

        if ($this->payLines() === []) {
            return;
        }

        $this->saleId ??= (string) Str::uuid7();
        $this->step = self::PAY;
        $this->message = null;
        $this->cashGiven = '';
        $this->resetAmounts();
    }

    /** Back to the cart, unless an M-Pesa payment already went through for this total. */
    public function backToCart(): void
    {
        if ($this->step !== self::PAY) {
            return;
        }

        if ($this->mpesaPaid !== []) {
            $this->message = __('terminal.sale.paid_in_part');

            return;
        }

        $this->step = self::CART;
        $this->message = null;
    }

    public function press(string $digit): void
    {
        if ($this->step === self::PAY && preg_match('/^\d$/', $digit) === 1 && strlen($this->cashGiven) < 7) {
            $this->cashGiven = ltrim($this->cashGiven.$digit, '0');
        }
    }

    public function backspace(): void
    {
        $this->cashGiven = substr($this->cashGiven, 0, -1);
    }

    /** A quick-cash button: the exact amount (rounded up to the shilling), or a note. */
    public function quickCash(int $shillings): void
    {
        $this->cashGiven = (string) $shillings;
    }

    public function requestMpesa(VendaApi $api): void
    {
        if ($this->step !== self::PAY || $this->saleId === null) {
            return;
        }

        $phone = PhoneNumber::normalize($this->phone);
        $amount = ctype_digit($this->mpesaAmount) ? (int) $this->mpesaAmount * 100 : 0;

        if ($phone === null) {
            $this->message = __('terminal.sale.mpesa.bad_phone');

            return;
        }

        if ($amount < 100 || $amount > $this->remainingCents()) {
            $this->message = __('terminal.sale.mpesa.bad_amount', ['max' => intdiv($this->remainingCents(), 100)]);

            return;
        }

        $uuid = (string) Str::uuid7();

        try {
            $api->requestPayment($uuid, $this->saleId, $phone, $amount);
        } catch (ApiRejected $e) {
            $this->message = __('terminal.sale.mpesa.refused.'.($e->reason ?? 'other'));

            return;
        } catch (ApiUnavailable|TillNotAuthorised|ApiNotConfigured|KeyringUnavailable) {
            $this->message = __('terminal.sale.mpesa.offline');

            return;
        }

        $this->waiting = ['uuid' => $uuid, 'amount_cents' => $amount, 'started_at' => now()->getTimestamp()];
        $this->step = self::MPESA;
        $this->message = null;
    }

    /** Polled while waiting on the customer's phone. */
    public function checkMpesa(VendaApi $api, RecordSale $recordSale, TillSession $session): void
    {
        if ($this->step !== self::MPESA || $this->waiting === null) {
            return;
        }

        try {
            $payment = $api->payment($this->waiting['uuid']);
        } catch (ApiRejected|ApiUnavailable|TillNotAuthorised|ApiNotConfigured|KeyringUnavailable) {
            // Keep waiting: the network may come back, and the server keeps checking with Safaricom.
            return;
        }

        if ($payment->isPending()) {
            return;
        }

        $this->step = self::PAY;

        if (! $payment->succeeded()) {
            $this->message = __('terminal.sale.mpesa.failed', ['reason' => $payment->failureReason ?? __('terminal.sale.mpesa.no_reason')]);
            $this->waiting = null;

            return;
        }

        $this->mpesaPaid[] = ['method' => 'mpesa_push', 'amount_cents' => $this->waiting['amount_cents'], 'payment_uuid' => $payment->uuid, 'reference' => $payment->providerReference];
        $this->waiting = null;
        $this->resetAmounts();

        // Paid in full by M-Pesa: nothing left to take.
        if ($this->remainingCents() === 0) {
            $this->complete($recordSale, $session);
        }
    }

    /**
     * Stop waiting and take the rest another way. If the customer pays after
     * all, the money is in the paybill without a sale: the owner sees it.
     */
    public function stopWaiting(): void
    {
        if ($this->step === self::MPESA && $this->waitedLongEnough()) {
            $this->step = self::PAY;
            $this->waiting = null;
            $this->message = __('terminal.sale.mpesa.stopped');
        }
    }

    /**
     * The customer paid straight to the paybill or till and shows the SMS.
     * Works offline: the server confirms the code against Safaricom's notice
     * later and flags it for the owner if it never comes (T3a, T3d).
     */
    public function addTypedCode(RecordSale $recordSale, TillSession $session): void
    {
        if ($this->step !== self::PAY) {
            return;
        }

        $code = strtoupper(preg_replace('/\s+/', '', $this->typedCode) ?? '');
        $amount = ctype_digit($this->codeAmount) ? (int) $this->codeAmount * 100 : 0;

        if (preg_match('/^[A-Z0-9]{10}$/', $code) !== 1) {
            $this->message = __('terminal.sale.code.bad_code');

            return;
        }

        if (in_array($code, array_column($this->mpesaPaid, 'reference'), true)) {
            $this->message = __('terminal.sale.code.duplicate');

            return;
        }

        if ($amount < 100 || $amount > $this->remainingCents()) {
            $this->message = __('terminal.sale.mpesa.bad_amount', ['max' => intdiv($this->remainingCents(), 100)]);

            return;
        }

        $this->mpesaPaid[] = ['method' => 'mpesa_code', 'amount_cents' => $amount, 'payment_uuid' => null, 'reference' => $code];
        $this->typedCode = '';
        $this->message = null;
        $this->resetAmounts();

        if ($this->remainingCents() === 0) {
            $this->complete($recordSale, $session);
        }
    }

    /** A typed code can be taken off again (a typo); a push that went through cannot. */
    public function removeTypedCode(int $index): void
    {
        if ($this->step === self::PAY && ($this->mpesaPaid[$index]['method'] ?? null) === 'mpesa_code') {
            $this->mpesaPaid = array_values(array_filter($this->mpesaPaid, fn (int $key) => $key !== $index, ARRAY_FILTER_USE_KEY));
            $this->resetAmounts();
        }
    }

    public function complete(RecordSale $recordSale, TillSession $session): void
    {
        $staff = $session->staff();

        if ($staff === null || $this->step !== self::PAY) {
            return;
        }

        $remaining = $this->remainingCents();
        $given = ctype_digit($this->cashGiven) ? (int) $this->cashGiven * 100 : 0;

        if ($remaining > 0 && $given < $remaining) {
            $this->message = __('terminal.sale.cash_short', ['amount' => Money::fromCents($remaining)->format()]);

            return;
        }

        $tenders = array_map(fn (array $paid) => TenderData::from($paid['method'] === 'mpesa_push'
            ? ['method' => 'mpesa_push', 'amount_cents' => $paid['amount_cents'], 'payment_uuid' => $paid['payment_uuid']]
            : ['method' => 'mpesa_code', 'amount_cents' => $paid['amount_cents'], 'mpesa_reference' => $paid['reference']]), $this->mpesaPaid);

        if ($remaining > 0) {
            $tenders[] = TenderData::from([
                'method' => 'cash',
                'amount_cents' => $remaining,
                'tendered_cents' => $given,
                'change_cents' => $given - $remaining,
            ]);
        }

        try {
            $order = $recordSale->handle($staff, $this->cartEntries(), $tenders, $this->saleId, $this->openTicketModel());
        } catch (SaleRefused $e) {
            $this->message = __('terminal.sale.refused.'.$e->reason);

            return;
        }

        $this->done = [
            'order_id' => $order->id,
            'total_cents' => $order->total_cents,
            'change_cents' => $remaining > 0 ? $given - $remaining : 0,
            'number' => $order->receipt_number ?? (string) $order->sequence_number,
        ];
        $this->step = self::DONE;
        $this->message = null;

        // After the sale is saved, never before: a printer problem must not lose a sale.
        SyncState::write(PrintReceiptJob::LAST_ERROR, null);
        PrintReceiptJob::dispatch($order->id, openDrawer: $remaining > 0);
    }

    /** Prints the last sale's receipt again (no drawer), marked as a reprint and kept for the owner's audit. */
    public function reprint(TillSession $session): void
    {
        $staff = $session->staff();

        if ($this->step !== self::DONE || $this->done === null || $staff === null || ! $staff->can(StaffMember::REPRINT_RECEIPTS)) {
            return;
        }

        ReceiptReprint::query()->create([
            'id' => (string) Str::uuid7(),
            'order_id' => $this->done['order_id'],
            'staff_id' => $staff->id,
            'reprinted_at' => now()->utc(),
        ]);

        PrintReceiptJob::dispatch($this->done['order_id'], reprint: true);
    }

    public function newSale(): void
    {
        $this->reset(['cart', 'step', 'saleId', 'mpesaPaid', 'waiting', 'cashGiven', 'phone', 'mpesaAmount', 'typedCode', 'codeAmount', 'message', 'done', 'ticketId', 'takeawayName', 'notice']);
    }

    /** Another till sent, billed or paid a ticket: redraw, and let go of one paid elsewhere. */
    #[On('till-updated')]
    public function tillUpdated(): void
    {
        if ($this->ticketId !== null && $this->step === self::CART && $this->openTicketModel() === null) {
            $this->ticketId = null;
            $this->message = __('terminal.tickets.paid_elsewhere');
        }
    }

    /** @return bool whether the order was sent */
    private function send(SendOrder $sendOrder, TillSession $session, ?Ticket $ticket, ?string $tableId = null, bool $keepTicket = false): bool
    {
        $staff = $session->staff();

        if ($staff === null) {
            return false;
        }

        try {
            $ticket = $sendOrder->handle($staff, $this->cartEntries(), $ticket, $tableId, $ticket === null ? $this->takeawayName : null);
        } catch (TicketRefused $e) {
            $this->message = __('terminal.tickets.refused.'.$e->reason);

            return false;
        }

        $this->cart = [];
        $this->takeawayName = '';
        $this->message = null;
        $this->step = self::CART;

        if ($keepTicket) {
            $this->ticketId = $ticket->id;
        } else {
            $this->ticketId = null;
            $this->notice = __('terminal.tickets.sent', ['ticket' => $ticket->title()]);
        }

        return true;
    }

    private function openTicketModel(): ?Ticket
    {
        return $this->ticketId === null ? null : Ticket::query()->unpaid()->with('lines')->find($this->ticketId);
    }

    /**
     * What is being paid: the ticket's sent lines at their sent prices, or the cart.
     *
     * @return list<OrderLineData>
     */
    private function payLines(): array
    {
        $ticket = $this->openTicketModel();

        return $ticket !== null
            ? array_map(fn (TicketLine $line) => $line->toOrderLine(), $ticket->billedLines())
            : array_column($this->lines(), 'data');
    }

    /** Both M-Pesa amount fields start at what is left, in whole shillings. */
    private function resetAmounts(): void
    {
        $this->mpesaAmount = (string) intdiv($this->remainingCents(), 100);
        $this->codeAmount = $this->mpesaAmount;
    }

    /** Polled while money is being taken, so the session does not time out mid-payment. */
    public function keepAlive(): void {}

    public function signOut(TillSession $session): void
    {
        if (in_array($this->step, [self::PAY, self::MPESA], true)) {
            return;
        }

        $session->signOut();
        $this->redirectRoute('sign-in');
    }

    public function render(Till $till, TillSession $session): View
    {
        $lines = $this->lines();
        $ticket = $this->openTicketModel();
        $sent = $ticket?->billedLines() ?? [];
        // What was sent plus the round in the cart; when paying, the cart is already on the ticket.
        $totals = OrderTotals::fromLines([...array_map(fn (TicketLine $line) => $line->toOrderLine(), $sent), ...array_column($lines, 'data')]);
        $remaining = $this->remainingCents($totals);
        $identity = $till->identity();

        return view('livewire.sale', [
            'identity' => $identity,
            'tableService' => $identity?->terminal_mode === TerminalMode::TableService->value,
            'ticket' => $ticket,
            'sent' => $sent,
            // Unpaid tickets, and paid ones the server refused lines on, until a manager deals with them.
            'openTickets' => $this->step === self::TICKETS
                ? Ticket::query()->with('lines')->where(fn ($query) => $query->where('status', '!=', Ticket::CLOSED)->orWhere('sync_status', Ticket::REJECTED))->orderBy('opened_at')->get()
                : collect(),
            'openTicketCount' => Ticket::query()->unpaid()->count(),
            'areas' => $this->step === self::TABLE ? FloorArea::query()->orderBy('sort_order')->orderBy('name')->get() : collect(),
            'staff' => $session->staff(),
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(),
            'items' => Item::query()->where('is_active', true)
                ->when($this->category !== null, fn ($query) => $query->where('category_id', $this->category))
                ->orderBy('name')->get(),
            'lines' => $lines,
            'totals' => $totals,
            'remaining' => Money::fromCents($remaining),
            'quickCash' => self::quickCashOptions($remaining),
            'online' => SyncStatus::current()->connection === SyncStatus::ONLINE,
            'waitedLongEnough' => $this->waitedLongEnough(),
            'printerReady' => PrinterSettings::current()->isSet(),
            'printError' => $this->step === self::DONE ? SyncState::read(PrintReceiptJob::LAST_ERROR) : null,
        ]);
    }

    /**
     * Exact (rounded up to the shilling), then the next notes above it.
     *
     * @return list<int> whole shillings
     */
    public static function quickCashOptions(int $remainingCents): array
    {
        if ($remainingCents <= 0) {
            return [];
        }

        $exact = intdiv($remainingCents + 99, 100);
        $options = [$exact];

        // The next amount above the exact one that each note makes.
        foreach ([50, 100, 200, 500, 1000] as $note) {
            $next = (intdiv($exact, $note) + 1) * $note;

            if (! in_array($next, $options, true)) {
                $options[] = $next;
            }
        }

        return array_slice($options, 0, 4);
    }

    /**
     * Cart lines priced from the till's menu; items switched off since are dropped.
     *
     * @return list<array{item: Item, quantity: int, data: OrderLineData}>
     */
    private function lines(): array
    {
        /** @var Collection<string, Item> $items */
        $items = Item::query()->whereKey(array_keys($this->cart))->where('is_active', true)->get()->keyBy('id');
        $lines = [];

        foreach ($this->cart as $itemId => $quantity) {
            $item = $items->get($itemId);

            if ($item === null) {
                continue;
            }

            $lines[] = ['item' => $item, 'quantity' => $quantity, 'data' => OrderLineData::from([
                'item_id' => $item->id,
                'name' => $item->name,
                'quantity' => $quantity,
                'unit_price_cents' => $item->price_cents,
                'vat_category' => $item->vat_category,
                'line_total_cents' => $item->price_cents * $quantity,
            ])];
        }

        return $lines;
    }

    /** @return list<array{item_id: string, quantity: int}> */
    private function cartEntries(): array
    {
        return array_map(fn (array $line) => ['item_id' => $line['item']->id, 'quantity' => $line['quantity']], $this->lines());
    }

    private function remainingCents(?OrderTotals $totals = null): int
    {
        $totals ??= OrderTotals::fromLines($this->payLines());

        return max(0, $totals->total->cents - array_sum(array_column($this->mpesaPaid, 'amount_cents')));
    }

    private function waitedLongEnough(): bool
    {
        return $this->waiting !== null && now()->getTimestamp() - $this->waiting['started_at'] >= self::MPESA_PATIENCE_SECONDS;
    }
}
