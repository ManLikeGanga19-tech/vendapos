<?php

declare(strict_types=1);

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Models\Payment;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;
use Illuminate\Support\Facades\Log;
use VendaPOS\Core\Enums\PaymentStatus;

/**
 * order.recorded (cancelled) → warn when the customer had already paid by
 * mobile money: the money was taken for a sale that never happened. Only a
 * flag for now; refunds are a later payments slice.
 */
final class FlagPaymentsForCancelledOrder extends TenantEventJob
{
    protected static function queueName(): string
    {
        return 'payments';
    }

    protected function process(DomainEvent $event): void
    {
        if (($event->payload['status'] ?? null) !== 'cancelled') {
            return;
        }

        Payment::query()
            ->where('order_id', $event->aggregate_id)
            ->where('status', PaymentStatus::Succeeded)
            ->get(['id', 'amount_cents', 'provider_reference'])
            ->each(fn (Payment $payment) => Log::warning('payments.paid_order_cancelled', [
                'tenant_id' => $event->tenant_id,
                'branch_id' => $event->branch_id,
                'order_id' => $event->aggregate_id,
                'payment_id' => $payment->id,
                'amount_cents' => $payment->amount_cents,
                'provider_reference' => $payment->provider_reference,
            ]));
    }
}
