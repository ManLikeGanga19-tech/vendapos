<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Models\Payment;
use VendaPOS\Core\Enums\PaymentStatus;

/** Stands in for every provider in tests, demos and local work without sandbox keys. */
final class FakeGateway extends MobileMoneyGateway
{
    public function initiate(Payment $payment): GatewayResult
    {
        return GatewayResult::accepted('fake-'.$payment->id);
    }

    public function query(Payment $payment): GatewayResult
    {
        return match (config('vendapos.payments.fake_outcome')) {
            'succeeded' => GatewayResult::succeeded('FAKE'.strtoupper(substr(md5($payment->id), 0, 6)), $payment->amount_cents),
            'failed' => GatewayResult::unsuccessful(PaymentStatus::Failed, 'fake_failure', 'Declined by the fake gateway.'),
            default => GatewayResult::pending(),
        };
    }
}
