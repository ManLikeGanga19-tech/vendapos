<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiRejected;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\VendaApi;
use App\Livewire\Concerns\FollowsTillLanguage;
use App\Livewire\Concerns\NeedsSignedInStaff;
use App\Livewire\Concerns\PicksLanguage;
use App\Models\Category;
use App\Models\Item;
use App\Models\StaffMember;
use App\Sales\RecordSale;
use App\Sales\SaleRefused;
use App\Security\KeyringUnavailable;
use App\Staff\TillSession;
use App\Sync\SyncStatus;
use App\Till\Till;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Rules\PhoneNumber;
use VendaPOS\Core\Tax\OrderTotals;
use VendaPOS\Core\ValueObjects\Money;

/**
 * The quick-sale screen: pick items (or scan them), then take cash, an
 * M-Pesa push, or both. M-Pesa pays whole shillings and cash the rest
 * (Daniel, T3a). The sale is written to the till first and synced after.
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

    /** How long the till waits on the customer's phone before offering to stop. */
    public const int MPESA_PATIENCE_SECONDS = 120;

    /** @var array<string, int> item ID => quantity, in the order added */
    public array $cart = [];

    public ?string $category = null;

    public string $step = self::CART;

    /** The sale's UUID, fixed once payment starts so an M-Pesa push and the order share it. */
    public ?string $saleId = null;

    /** @var list<array{payment_uuid: string, amount_cents: int, reference: string|null}> M-Pesa payments that succeeded */
    public array $mpesaPaid = [];

    /** @var array{uuid: string, amount_cents: int, started_at: int}|null the push being waited on */
    public ?array $waiting = null;

    /** Cash handed over, in whole shillings as typed. */
    public string $cashGiven = '';

    public string $phone = '';

    /** In whole shillings. */
    public string $mpesaAmount = '';

    public ?string $message = null;

    /** @var array{total_cents: int, change_cents: int, sequence: int}|null */
    public ?array $done = null;

    public function add(string $itemId): void
    {
        if ($this->step !== self::CART || ! Item::query()->whereKey($itemId)->where('is_active', true)->exists()) {
            return;
        }

        $this->cart[$itemId] = ($this->cart[$itemId] ?? 0) + 1;
        $this->message = null;
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

    public function startPayment(TillSession $session): void
    {
        if ($this->cart === []) {
            return;
        }

        if (! $session->can(StaffMember::TAKE_PAYMENTS)) {
            $this->message = __('terminal.sale.refused.not_allowed');

            return;
        }

        $this->saleId ??= (string) Str::uuid7();
        $this->step = self::PAY;
        $this->message = null;
        $this->cashGiven = '';
        $this->mpesaAmount = (string) intdiv($this->remainingCents(), 100);
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

        $this->mpesaPaid[] = ['payment_uuid' => $payment->uuid, 'amount_cents' => $this->waiting['amount_cents'], 'reference' => $payment->providerReference];
        $this->waiting = null;
        $this->mpesaAmount = (string) intdiv($this->remainingCents(), 100);

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

        $tenders = array_map(fn (array $paid) => TenderData::from([
            'method' => 'mpesa_push',
            'amount_cents' => $paid['amount_cents'],
            'payment_uuid' => $paid['payment_uuid'],
        ]), $this->mpesaPaid);

        if ($remaining > 0) {
            $tenders[] = TenderData::from([
                'method' => 'cash',
                'amount_cents' => $remaining,
                'tendered_cents' => $given,
                'change_cents' => $given - $remaining,
            ]);
        }

        try {
            $order = $recordSale->handle($staff, $this->cartEntries(), $tenders, $this->saleId);
        } catch (SaleRefused $e) {
            $this->message = __('terminal.sale.refused.'.$e->reason);

            return;
        }

        $this->done = [
            'total_cents' => $order->total_cents,
            'change_cents' => $remaining > 0 ? $given - $remaining : 0,
            'sequence' => $order->sequence_number,
        ];
        $this->step = self::DONE;
        $this->message = null;
    }

    public function newSale(): void
    {
        $this->reset(['cart', 'step', 'saleId', 'mpesaPaid', 'waiting', 'cashGiven', 'phone', 'mpesaAmount', 'message', 'done']);
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
        $totals = OrderTotals::fromLines(array_column($lines, 'data'));
        $remaining = $this->remainingCents($totals);

        return view('livewire.sale', [
            'identity' => $till->identity(),
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
        $totals ??= OrderTotals::fromLines(array_column($this->lines(), 'data'));

        return max(0, $totals->total->cents - array_sum(array_column($this->mpesaPaid, 'amount_cents')));
    }

    private function waitedLongEnough(): bool
    {
        return $this->waiting !== null && now()->getTimestamp() - $this->waiting['started_at'] >= self::MPESA_PATIENCE_SECONDS;
    }
}
