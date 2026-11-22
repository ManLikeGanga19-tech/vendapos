<?php

declare(strict_types=1);

namespace App\Sales;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderTender;
use Carbon\CarbonImmutable;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Enums\TenderMethod;

/**
 * One sale on the "Sales today" screen, whether this till made it (from its
 * own database) or another till of the branch did (from the server).
 */
final readonly class SaleSummary
{
    /**
     * @param  list<array{name: string, quantity: int, line_total_cents: int}>  $lines
     * @param  list<array{method: string, amount_cents: int, mpesa_reference: ?string}>  $tenders
     */
    public function __construct(
        public string $id,
        public ?string $receiptNumber,
        public CarbonImmutable $placedAt,
        public ?string $staffId,
        public string $status,
        public int $totalCents,
        public array $lines,
        public array $tenders,
        // Made on this till, so it is in this till's own database.
        public bool $mine,
        public ?string $voidReason = null,
    ) {}

    public static function fromOrder(Order $order): self
    {
        return new self(
            id: $order->id,
            receiptNumber: $order->receipt_number,
            placedAt: CarbonImmutable::instance($order->placed_at),
            staffId: $order->staff_id,
            status: $order->status,
            totalCents: $order->total_cents,
            lines: array_values($order->lines->map(fn (OrderLine $line) => [
                'name' => $line->name,
                'quantity' => $line->quantity,
                'line_total_cents' => $line->line_total_cents,
            ])->all()),
            tenders: array_values($order->tenders->map(fn (OrderTender $tender) => [
                'method' => $tender->method,
                'amount_cents' => $tender->amount_cents,
                'mpesa_reference' => $tender->mpesa_reference,
            ])->all()),
            mine: true,
            voidReason: $order->void_reason,
        );
    }

    /** @param  array<string, mixed>  $sale  one entry of GET /api/v1/orders/today */
    public static function fromServer(array $sale, bool $mine): self
    {
        return new self(
            id: (string) $sale['id'],
            receiptNumber: is_string($sale['receipt_number'] ?? null) ? $sale['receipt_number'] : null,
            placedAt: CarbonImmutable::parse((string) $sale['placed_at']),
            staffId: is_string($sale['staff_id'] ?? null) ? $sale['staff_id'] : null,
            status: (string) $sale['status'],
            totalCents: (int) $sale['total_cents'],
            lines: array_values(array_map(fn (array $line) => [
                'name' => (string) $line['name'],
                'quantity' => (int) $line['quantity'],
                'line_total_cents' => (int) $line['line_total_cents'],
            ], array_filter((array) ($sale['lines'] ?? []), is_array(...)))),
            tenders: array_values(array_map(fn (array $tender) => [
                'method' => (string) $tender['method'],
                'amount_cents' => (int) $tender['amount_cents'],
                'mpesa_reference' => is_string($tender['mpesa_reference'] ?? null) ? $tender['mpesa_reference'] : null,
            ], array_filter((array) ($sale['tenders'] ?? []), is_array(...)))),
            mine: $mine,
            voidReason: is_string(data_get($sale, 'void.reason')) ? data_get($sale, 'void.reason') : null,
        );
    }

    public function isVoided(): bool
    {
        return $this->status === OrderStatus::Voided->value;
    }

    public function cashCents(): int
    {
        return $this->paidBy(TenderMethod::Cash);
    }

    public function mpesaCents(): int
    {
        return $this->paidBy(TenderMethod::MpesaPush) + $this->paidBy(TenderMethod::MpesaCode);
    }

    /** Whether $term is (part of) its receipt number or one of its M-Pesa codes. */
    public function matches(string $term): bool
    {
        $term = strtoupper(trim($term));

        if ($term === '') {
            return true;
        }

        $haystacks = [strtoupper((string) $this->receiptNumber), ...array_map(fn (array $tender) => strtoupper((string) $tender['mpesa_reference']), $this->tenders)];

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains($haystack, $term)) {
                return true;
            }
        }

        return false;
    }

    private function paidBy(TenderMethod $method): int
    {
        return array_sum(array_map(
            fn (array $tender) => $tender['method'] === $method->value ? $tender['amount_cents'] : 0,
            $this->tenders,
        ));
    }
}
