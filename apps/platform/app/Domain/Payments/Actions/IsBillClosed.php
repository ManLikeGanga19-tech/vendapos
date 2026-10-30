<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Models\Payment;
use VendaPOS\Core\Enums\PaymentChannel;
use VendaPOS\Core\Enums\PaymentStatus;

/**
 * For C2B validation: is the account number a customer is about to pay to a
 * bill's QR reference whose bill is already paid, or closed? Any other account
 * number is not ours to judge.
 */
final class IsBillClosed
{
    public function handle(string $billRef, int $paymentAccountId): bool
    {
        return Payment::query()
            ->where('channel', PaymentChannel::Qr)
            ->where('reference', strtoupper(trim($billRef)))
            ->where('payment_account_id', $paymentAccountId)
            ->where('status', '!=', PaymentStatus::Pending)
            ->exists();
    }
}
