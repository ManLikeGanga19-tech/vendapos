<?php

declare(strict_types=1);

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Actions\ApplyPaymentOutcome;
use App\Domain\Payments\Gateways\PaymentGateways;
use App\Domain\Payments\Models\Payment;
use App\Infrastructure\Webhooks\WebhookReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies one logged provider callback inside its tenant. The callback is
 * trusted only if it parses and names the provider request ID this payment
 * got when it was pushed; anything else is rejected and kept for review.
 */
final class ProcessPaymentCallback implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $receiptId,
    ) {
        $this->onQueue('payments');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(PaymentGateways $gateways, ApplyPaymentOutcome $applyOutcome): void
    {
        $previous = tenant();
        tenancy()->initialize($this->tenantId);

        try {
            $receipt = WebhookReceipt::query()->findOrFail($this->receiptId);

            if ($receipt->status !== WebhookReceipt::RECEIVED || $receipt->tenant_id !== $this->tenantId || $receipt->payment_id === null) {
                return;
            }

            // Waits for the push's transaction, so a fast callback still sees the stored request ID.
            $payment = DB::transaction(fn () => Payment::query()->whereKey($receipt->payment_id)->lockForUpdate()->firstOrFail());

            if ($payment->provider_request_id === null && ! $payment->status->isFinal()) {
                $this->release(10);

                return;
            }

            $parsed = $gateways->for($payment->provider)->parseCallback((array) json_decode($receipt->body, true));

            if ($parsed === null) {
                $receipt->reject('unparseable');

                return;
            }

            if (! hash_equals((string) $payment->provider_request_id, $parsed['requestId'])) {
                Log::channel('security')->warning('payments.callback_request_id_mismatch', [
                    'tenant_id' => $this->tenantId,
                    'payment_id' => $payment->id,
                    'receipt_id' => $receipt->id,
                ]);
                $receipt->reject('request_id_mismatch');

                return;
            }

            $applyOutcome->handle($payment->id, $parsed['result']);
            $receipt->markProcessed();
        } finally {
            $previous === null ? tenancy()->end() : tenancy()->initialize($previous);
        }
    }
}
