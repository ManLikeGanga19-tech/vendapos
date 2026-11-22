<?php

declare(strict_types=1);

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Models\MpesaCheck;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;
use VendaPOS\Core\Enums\TenderMethod;

/**
 * order.voided, or order.recorded for a sale voided before it synced → a sale
 * paid by M-Pesa was voided, so the customer's money has to go back. Nothing
 * sends it back automatically (Daraja's reversal needs its own approval): the
 * owner sees "M-Pesa to refund" and marks it checked once they have paid it.
 * Reads only the event's snapshot, never the Orders tables.
 */
final class FlagVoidedSalePaid extends TenantEventJob
{
    protected static function queueName(): string
    {
        return 'payments';
    }

    protected function process(DomainEvent $event): void
    {
        $payload = $event->payload;

        if (($payload['status'] ?? null) !== 'voided') {
            return;
        }

        $mobile = [TenderMethod::MpesaPush->value, TenderMethod::MpesaCode->value];
        /** @var list<array<string, mixed>> $tenders */
        $tenders = array_values(array_filter(
            (array) ($payload['tenders'] ?? []),
            fn (mixed $tender) => is_array($tender) && in_array($tender['method'] ?? null, $mobile, true),
        ));

        if ($tenders === []) {
            return;
        }

        $codes = array_values(array_filter(array_map(fn (array $tender) => $tender['mpesa_reference'] ?? null, $tenders), is_string(...)));
        $payments = array_values(array_filter(array_map(fn (array $tender) => $tender['payment_id'] ?? null, $tenders), is_string(...)));
        /** @var array<string, mixed> $void */
        $void = (array) ($payload['void'] ?? []);

        MpesaCheck::open(MpesaCheck::VOIDED_SALE_PAID, (string) $event->aggregate_id, [
            'branch_id' => $event->branch_id,
            'order_id' => $event->aggregate_id,
            'mpesa_code' => count($codes) === 1 ? $codes[0] : null,
            'payment_id' => count($payments) === 1 ? $payments[0] : null,
            'amount_cents' => array_sum(array_map(fn (array $tender) => (int) ($tender['amount_cents'] ?? 0), $tenders)),
            'details' => [
                'receipt_number' => $payload['receipt_number'] ?? null,
                'reason' => $void['reason'] ?? null,
                'mpesa_codes' => $codes,
                'payment_ids' => $payments,
            ],
        ]);
    }
}
