<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Models\MpesaCheck;
use App\Domain\Payments\Models\MpesaCodeClaim;
use App\Domain\Payments\Models\MpesaConfirmation;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use VendaPOS\Core\Enums\PaymentChannel;
use VendaPOS\Core\Enums\PaymentStatus;

/**
 * Checks M-Pesa money against the sales that say they took it (T3d). Typed
 * codes are accepted offline and confirmed here when Safaricom's C2B
 * confirmation arrives, before or after the sale syncs. Anything that does
 * not add up becomes an open check for the owner; nothing is auto-accepted
 * or auto-rejected (Daniel, T3a/T3d).
 */
final class MatchMpesaCodes
{
    /**
     * The tenders of a synced sale: typed codes become claims, pushes are marked claimed.
     *
     * @param  list<array<string, mixed>>  $tenders  as in the order.recorded snapshot
     */
    public function recordSale(string $orderId, string $branchId, array $tenders): void
    {
        foreach ($tenders as $tender) {
            if (($tender['method'] ?? null) === 'mpesa_push' && is_string($tender['payment_id'] ?? null)) {
                $this->claimPush($tender['payment_id']);
            }

            if (($tender['method'] ?? null) === 'mpesa_code' && is_string($tender['mpesa_reference'] ?? null)) {
                $this->claimCode($orderId, $branchId, $tender['mpesa_reference'], (int) ($tender['amount_cents'] ?? 0));
            }
        }
    }

    /** A confirmation arrived: pay the bill whose QR it was for, and settle every claim waiting on its code. */
    public function confirmationArrived(MpesaConfirmation $confirmation): void
    {
        $this->settleQr($confirmation);

        MpesaCodeClaim::query()
            ->where('mpesa_code', $confirmation->trans_id)
            ->whereIn('status', [MpesaCodeClaim::PENDING, MpesaCodeClaim::UNCONFIRMED])
            ->get()
            ->each(fn (MpesaCodeClaim $claim) => $this->settle($claim, $confirmation));
    }

    /**
     * The periodic sweep: typed codes still unconfirmed after the grace
     * period, and paid pushes no sale has claimed.
     */
    public function sweep(int $graceMinutes): void
    {
        $cutoff = now()->subMinutes($graceMinutes);

        MpesaCodeClaim::query()
            ->where('status', MpesaCodeClaim::PENDING)
            ->where('created_at', '<', $cutoff)
            ->get()
            ->each(function (MpesaCodeClaim $claim) {
                $claim->update(['status' => MpesaCodeClaim::UNCONFIRMED, 'checked_at' => now()]);
                MpesaCheck::open(MpesaCheck::UNCONFIRMED_CODE, "{$claim->order_id}:{$claim->mpesa_code}", [
                    'branch_id' => $claim->branch_id,
                    'order_id' => $claim->order_id,
                    'mpesa_code' => $claim->mpesa_code,
                    'amount_cents' => $claim->amount_cents,
                ]);
            });

        Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->whereNull('claimed_at')
            ->where('completed_at', '<', $cutoff)
            ->get()
            ->each(fn (Payment $payment) => MpesaCheck::open(MpesaCheck::UNCLAIMED_PUSH, $payment->id, [
                'branch_id' => $payment->branch_id,
                'order_id' => $payment->order_id,
                'mpesa_code' => $payment->provider_reference !== null ? substr($payment->provider_reference, 0, 10) : null,
                'payment_id' => $payment->id,
                'amount_cents' => $payment->amount_cents,
            ]));
    }

    /**
     * The account number the customer paid to is a QR payment's reference, on
     * the same account. The right amount pays it, and the till sees it as a paid
     * push; anything else leaves it pending and opens a check for the owner.
     */
    private function settleQr(MpesaConfirmation $confirmation): void
    {
        if ($confirmation->bill_ref === null) {
            return;
        }

        $payment = Payment::query()
            ->where('channel', PaymentChannel::Qr)
            ->where('reference', strtoupper(trim($confirmation->bill_ref)))
            ->where('payment_account_id', $confirmation->payment_account_id)
            ->lockForUpdate()
            ->first();

        if ($payment === null || $payment->provider_reference === $confirmation->trans_id) {
            return;
        }

        $check = [
            'branch_id' => $payment->branch_id,
            'order_id' => $payment->order_id,
            'mpesa_code' => $confirmation->trans_id,
            'payment_id' => $payment->id,
            'amount_cents' => $payment->amount_cents,
        ];

        if ($payment->status !== PaymentStatus::Pending) {
            // Paid already (or given up on): this is a second payment of the same bill.
            MpesaCheck::open(MpesaCheck::QR_PAID_TWICE, "{$payment->id}:{$confirmation->trans_id}", [...$check, 'amount_cents' => $confirmation->amount_cents]);

            return;
        }

        if ($confirmation->amount_cents !== $payment->amount_cents) {
            MpesaCheck::open(MpesaCheck::AMOUNT_MISMATCH, "{$payment->id}:{$confirmation->trans_id}", [
                ...$check,
                'details' => ['confirmed_cents' => $confirmation->amount_cents, 'paid_at' => Carbon::parse($confirmation->paid_at)->toIso8601String()],
            ]);

            return;
        }

        app(ApplyPaymentOutcome::class)->handle($payment->id, GatewayResult::succeeded($confirmation->trans_id, $confirmation->amount_cents));
    }

    private function claimPush(string $paymentId): void
    {
        $claimed = Payment::query()->whereKey($paymentId)->whereNull('claimed_at')->update(['claimed_at' => now()]);

        // A sale that synced late settles the check its absence opened.
        if ($claimed === 1) {
            $this->resolveAutomatically(MpesaCheck::UNCLAIMED_PUSH, $paymentId);
        }
    }

    private function claimCode(string $orderId, string $branchId, string $code, int $amountCents): void
    {
        // Two tills syncing the same code at once must see each other: one at a time per code,
        // until the caller's transaction ends.
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["mpesa_code:{$code}"]);

        $claim = MpesaCodeClaim::query()->firstOrCreate(
            ['order_id' => $orderId, 'mpesa_code' => $code],
            ['branch_id' => $branchId, 'amount_cents' => $amountCents, 'status' => MpesaCodeClaim::PENDING, 'created_at' => now()],
        );

        if (! $claim->wasRecentlyCreated) {
            return;
        }

        // Offline tills cannot know about each other: both sales stand, and both are flagged.
        $others = MpesaCodeClaim::query()->where('mpesa_code', $code)->where('order_id', '!=', $orderId)->get();

        if ($others->isNotEmpty()) {
            MpesaCodeClaim::query()->where('mpesa_code', $code)->update(['status' => MpesaCodeClaim::REUSED, 'checked_at' => now()]);
            MpesaCheck::open(MpesaCheck::CODE_REUSED, $code, [
                'branch_id' => $branchId,
                'mpesa_code' => $code,
                'details' => ['order_ids' => [...$others->pluck('order_id')->all(), $orderId]],
            ]);

            return;
        }

        $confirmation = MpesaConfirmation::query()->where('trans_id', $code)->first();

        if ($confirmation !== null) {
            $this->settle($claim, $confirmation);
        }
    }

    private function settle(MpesaCodeClaim $claim, MpesaConfirmation $confirmation): void
    {
        $matches = $claim->amount_cents === $confirmation->amount_cents;

        $claim->update([
            'status' => $matches ? MpesaCodeClaim::CONFIRMED : MpesaCodeClaim::AMOUNT_MISMATCH,
            'mpesa_confirmation_id' => $confirmation->id,
            'checked_at' => now(),
        ]);

        if ($matches) {
            // Confirmed after the grace period: the owner no longer needs to chase it.
            $this->resolveAutomatically(MpesaCheck::UNCONFIRMED_CODE, "{$claim->order_id}:{$claim->mpesa_code}");

            return;
        }

        MpesaCheck::open(MpesaCheck::AMOUNT_MISMATCH, "{$claim->order_id}:{$claim->mpesa_code}", [
            'branch_id' => $claim->branch_id,
            'order_id' => $claim->order_id,
            'mpesa_code' => $claim->mpesa_code,
            'amount_cents' => $claim->amount_cents,
            'details' => ['confirmed_cents' => $confirmation->amount_cents, 'paid_at' => Carbon::parse($confirmation->paid_at)->toIso8601String()],
        ]);
    }

    private function resolveAutomatically(string $kind, string $key): void
    {
        MpesaCheck::query()
            ->where('dedupe_key', "{$kind}:{$key}")
            ->where('status', MpesaCheck::OPEN)
            ->update(['status' => MpesaCheck::RESOLVED, 'resolved_note' => 'settled_automatically', 'resolved_at' => now()]);
    }
}
