<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Events\PaymentCompleted;
use App\Domain\Payments\Models\Payment;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use VendaPOS\Core\Enums\PaymentStatus;

/**
 * Applies a provider's answer (from a push, callback or status query) to a
 * payment. Only a pending payment changes, so the first final answer wins and
 * replays are no-ops. A success whose amount differs from what was asked for
 * is never applied: the payment stays pending for a status query to settle.
 */
final class ApplyPaymentOutcome
{
    public function handle(string $paymentId, GatewayResult $result): Payment
    {
        return DB::transaction(function () use ($paymentId, $result) {
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->status->isFinal() || $result->status === PaymentStatus::Pending) {
                return $payment;
            }

            if ($result->status === PaymentStatus::Succeeded
                && $result->amountCents !== null
                && $result->amountCents !== $payment->amount_cents) {
                Log::channel('security')->warning('payments.amount_mismatch', [
                    'payment_id' => $payment->id,
                    'expected_cents' => $payment->amount_cents,
                    'reported_cents' => $result->amountCents,
                ]);

                return $payment;
            }

            $payment->update([
                'status' => $result->status,
                'provider_reference' => $result->reference,
                'failure_code' => $result->failureCode,
                'failure_reason' => $result->failureReason,
                'completed_at' => now(),
            ]);

            $tenantId = (string) tenant()?->getTenantKey();

            $event = DomainEvent::record(
                type: 'payment.'.$payment->status->value,
                tenantId: $tenantId,
                payload: [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'provider' => $payment->provider->value,
                    'status' => $payment->status->value,
                    'amount_cents' => $payment->amount_cents,
                    'currency' => $payment->currency,
                    'provider_reference' => $payment->provider_reference,
                    'failure_code' => $payment->failure_code,
                ],
                aggregateId: $payment->id,
                branchId: $payment->branch_id,
                deviceId: $payment->device_id,
            );

            PaymentCompleted::dispatch($event->event_id, $tenantId, $payment->id, $payment->status);

            return $payment;
        });
    }
}
