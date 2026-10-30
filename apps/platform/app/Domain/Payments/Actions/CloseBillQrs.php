<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Models\Payment;
use VendaPOS\Core\Enums\PaymentChannel;
use VendaPOS\Core\Enums\PaymentStatus;

/**
 * The sale is paid, so every QR still open for it (older bills of the same
 * ticket) closes. A customer who scans one after this is declined where
 * Safaricom validates payments, and flagged as a bill paid twice otherwise.
 */
final class CloseBillQrs
{
    public function __construct(private readonly ApplyPaymentOutcome $applyOutcome) {}

    public function handle(string $orderId): void
    {
        Payment::query()
            ->where('channel', PaymentChannel::Qr)
            ->where('order_id', $orderId)
            ->where('status', PaymentStatus::Pending)
            ->pluck('id')
            ->each(fn (string $paymentId) => $this->applyOutcome->handle(
                $paymentId,
                GatewayResult::unsuccessful(PaymentStatus::Cancelled, 'bill_paid', 'The bill was paid another way.'),
            ));
    }
}
