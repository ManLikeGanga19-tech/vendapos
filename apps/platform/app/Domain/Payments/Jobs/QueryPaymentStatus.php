<?php

declare(strict_types=1);

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Actions\ApplyPaymentOutcome;
use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Gateways\PaymentGateways;
use App\Domain\Payments\Models\Payment;
use App\Infrastructure\Queue\LiveTenants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use VendaPOS\Core\Enums\PaymentStatus;

/**
 * Settles a pending payment whose callback is late or missing (CLAUDE.md §4)
 * by asking the provider. Past the give-up window a payment times out, whether
 * the provider keeps answering "still processing" or cannot be asked at all,
 * so the till stops waiting; a later "paid" is still recorded (ApplyPaymentOutcome).
 * When the provider says we ask too often (429), the payment is not asked
 * again for a while.
 */
final class QueryPaymentStatus implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 120;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $paymentId,
    ) {
        $this->onQueue('payments');
    }

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->paymentId}";
    }

    public function handle(PaymentGateways $gateways, ApplyPaymentOutcome $applyOutcome): void
    {
        // Purged since this was queued: nothing left to act on.
        if (! LiveTenants::has($this->tenantId)) {
            return;
        }

        $previous = tenant();
        tenancy()->initialize($this->tenantId);

        try {
            $payment = Payment::query()->find($this->paymentId);

            if ($payment === null || $payment->status->isFinal()) {
                return;
            }

            if ($payment->provider_request_id === null) {
                $this->failIfNeverPushed($applyOutcome);

                return;
            }

            $giveUpAt = $payment->initiated_at?->copy()->addMinutes((int) config('vendapos.payments.reconcile.give_up_after_minutes'));
            $pastGiveUp = $giveUpAt !== null && now()->greaterThan($giveUpAt);

            try {
                $result = $gateways->for($payment->provider)->query($payment);
            } catch (Throwable $e) {
                Log::warning('payments.status_query_failed', ['tenant_id' => $this->tenantId, 'payment_id' => $payment->id, 'error' => $e->getMessage()]);

                if ($e instanceof RequestException && $e->response->status() === 429) {
                    $payment->update(['query_backoff_until' => now()->addSeconds((int) config('vendapos.payments.reconcile.backoff_seconds'))]);
                }

                if ($pastGiveUp) {
                    $applyOutcome->handle($payment->id, GatewayResult::unsuccessful(PaymentStatus::TimedOut, 'no_answer', 'The provider did not answer in time.'));
                }

                return;
            }

            if ($result->status === PaymentStatus::Pending && $pastGiveUp) {
                $result = GatewayResult::unsuccessful(PaymentStatus::TimedOut, 'no_final_status', 'The provider gave no final answer in time.');
            }

            $applyOutcome->handle($payment->id, $result);
        } finally {
            $previous === null ? tenancy()->end() : tenancy()->initialize($previous);
        }
    }

    /** The push never went out (queue down, worker crash). Failing it under the row lock also stops a late push. */
    private function failIfNeverPushed(ApplyPaymentOutcome $applyOutcome): void
    {
        DB::transaction(function () use ($applyOutcome) {
            $payment = Payment::query()->whereKey($this->paymentId)->lockForUpdate()->firstOrFail();
            $cutoff = now()->subMinutes((int) config('vendapos.payments.reconcile.never_pushed_after_minutes'));

            if ($payment->provider_request_id === null && ! $payment->status->isFinal() && $payment->created_at->lessThan($cutoff)) {
                $applyOutcome->handle($payment->id, GatewayResult::unsuccessful(PaymentStatus::Failed, 'never_initiated', 'The payment request was never sent.'));
            }
        });
    }
}
