<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Enums\PaymentType;
use VendaPOS\Core\Payments\Tenders;
use VendaPOS\Core\ValueObjects\Money;

/**
 * A sale as the terminal sends it. Tenant, branch and device are not part of
 * the payload: the server takes them from the authenticated device, never
 * from the body (CLAUDE.md §3.1).
 */
final class OrderData extends Data
{
    /**
     * @param  DataCollection<int, OrderLineData>  $lines
     * @param  DataCollection<int, TenderData>  $tenders
     */
    public function __construct(
        // Client-generated; the idempotency key for sync.
        public string $uuid,
        // Per-device, strictly increasing; orders offline sales and later feeds eTIMS.
        public int $sequence_number,
        // When the sale happened on the terminal, ISO 8601.
        public string $placed_at,
        // The signed-in staff member who made the sale.
        public string $staff_id,
        public OrderStatus $status,
        #[DataCollectionOf(OrderLineData::class)]
        public DataCollection $lines,
        // How it was paid; the order's payment type is derived from these, never sent.
        #[DataCollectionOf(TenderData::class)]
        public DataCollection $tenders,
        public int $subtotal_cents,
        public int $vat_cents,
        public int $total_cents,
        // The ticket this sale pays (table service); null for a quick sale.
        public ?string $ticket_uuid = null,
        // Who voided it, who approved it and why: sent with a voided order, and only then.
        public ?VoidData $void = null,
        // The till drawer's shift the sale was made in. Null only from tills older than
        // cash-up: the server flags such a sale for the owner rather than refusing it,
        // because by the time it syncs the money has already changed hands.
        public ?string $shift_id = null,
    ) {}

    /** @return list<TenderData> */
    public function tenderList(): array
    {
        return array_values($this->tenders->all());
    }

    /** Cash, M-Pesa or split; null for a cancelled sale, which has no tenders. */
    public function paymentType(): ?PaymentType
    {
        return Tenders::paymentType($this->tenderList());
    }

    public function subtotal(): Money
    {
        return Money::fromCents($this->subtotal_cents);
    }

    public function vat(): Money
    {
        return Money::fromCents($this->vat_cents);
    }

    public function total(): Money
    {
        return Money::fromCents($this->total_cents);
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'uuid'],
            'sequence_number' => ['required', 'integer', 'min:1'],
            'placed_at' => ['required', 'date'],
            'staff_id' => ['required', 'string', 'uuid'],
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'tenders' => ['present', 'array', 'max:'.Tenders::MAX_TENDERS],
            'subtotal_cents' => ['required', 'integer', 'min:0'],
            'vat_cents' => ['required', 'integer', 'min:0'],
            'total_cents' => ['required', 'integer', 'min:0'],
            'ticket_uuid' => ['sometimes', 'nullable', 'string', 'uuid'],
            'shift_id' => ['sometimes', 'nullable', 'string', 'uuid'],
            'void' => ['required_if:status,'.OrderStatus::Voided->value, 'prohibited_unless:status,'.OrderStatus::Voided->value, 'array'],
        ];
    }

    /**
     * Order and line rules together, for validating one raw order payload.
     *
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        $rules = self::rules();

        foreach (OrderLineData::rules() as $field => $lineRules) {
            $rules["lines.*.{$field}"] = $lineRules;
        }

        foreach (TenderData::rules() as $field => $tenderRules) {
            $rules["tenders.*.{$field}"] = $tenderRules;
        }

        // Checked only on a voided order; on any other, "void" itself is refused above.
        foreach (VoidData::rules('void.') as $field => $voidRules) {
            $rules[$field] = ['exclude_unless:status,'.OrderStatus::Voided->value, ...$voidRules];
        }

        return $rules;
    }
}
