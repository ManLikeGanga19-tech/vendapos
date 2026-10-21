<?php

declare(strict_types=1);

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Actions\ApplyPaymentOutcome;
use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Gateways\PaymentGateways;
use App\Domain\Payments\Models\Payment;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;
use Illuminate\Support\Facades\Log;
use Throwable;
use VendaPOS\Core\Enums\PaymentStatus;

/**
 * payment.requested → push the request to the customer's phone. Runs once:
 * a retried push could prompt the customer twice, so any error fails the
 * payment and the cashier asks again with a new payment.
 */
final class InitiatePayment extends TenantEventJob
{
    public int $tries = 1;

    protected static function queueName(): string
    {
        return 'payments';
    }

    protected function process(DomainEvent $event): void
    {
        $payment = Payment::query()->whereKey($event->payload['payment_id'])->lockForUpdate()->firstOrFail();

        if ($payment->status->isFinal() || $payment->provider_request_id !== null) {
            return;
        }

        try {
            $result = app(PaymentGateways::class)->for($payment->provider)->initiate($payment);
        } catch (Throwable $e) {
            Log::error('payments.initiate_failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            // Without the provider's request ID this payment can never be queried, so it fails here
            // and the cashier sees it at once instead of waiting on a payment nobody can confirm.
            $result = GatewayResult::unsuccessful(PaymentStatus::Failed, 'initiation_error', 'The payment request could not be sent.');
        }

        if ($result->status === PaymentStatus::Pending) {
            $payment->update(['provider_request_id' => $result->requestId, 'initiated_at' => now()]);

            return;
        }

        app(ApplyPaymentOutcome::class)->handle($payment->id, $result);
    }
}
