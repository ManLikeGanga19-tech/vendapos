<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Models\Payment;
use VendaPOS\Core\Enums\PaymentStatus;

/**
 * For Orders: the payments a sale's M-Pesa push tenders name, as far as one
 * device may claim them. A payment another till started is reported as
 * missing, so a till cannot learn about payments that are not its own.
 */
final class FindTenderPayments
{
    /**
     * @param  list<string>  $paymentIds
     * @return array<string, array{amount_cents: int, succeeded: bool, order_id: string|null}>
     */
    public function handle(array $paymentIds, string $deviceId): array
    {
        if ($paymentIds === []) {
            return [];
        }

        return Payment::query()
            ->whereKey($paymentIds)
            ->where('device_id', $deviceId)
            ->get(['id', 'amount_cents', 'status', 'order_id'])
            ->mapWithKeys(fn (Payment $payment) => [$payment->id => [
                'amount_cents' => $payment->amount_cents,
                'succeeded' => $payment->status === PaymentStatus::Succeeded,
                'order_id' => $payment->order_id,
            ]])
            ->all();
    }
}
