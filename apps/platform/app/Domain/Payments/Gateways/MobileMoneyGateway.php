<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Models\Payment;

/**
 * One mobile-money provider's API. An abstract class rather than an interface
 * because everything under App\Domain must be a class (arch test).
 */
abstract class MobileMoneyGateway
{
    /** Pushes the payment request to the customer's phone. Never retried automatically. */
    abstract public function initiate(Payment $payment): GatewayResult;

    /** Asks the provider for the payment's current state (late or missing callbacks). */
    abstract public function query(Payment $payment): GatewayResult;
}
