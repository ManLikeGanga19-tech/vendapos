<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use LogicException;
use VendaPOS\Core\Enums\MobileMoneyProvider;

/** Picks the gateway for a provider from config('vendapos.payments'). */
final class PaymentGateways
{
    public function for(MobileMoneyProvider $provider): MobileMoneyGateway
    {
        if (config('vendapos.payments.fake')) {
            return app(FakeGateway::class);
        }

        $class = config("vendapos.payments.gateways.{$provider->value}");

        if (! is_string($class) || ! is_a($class, MobileMoneyGateway::class, true)) {
            throw new LogicException("No payment gateway is configured for {$provider->value}.");
        }

        /** @var MobileMoneyGateway */
        return app($class);
    }
}
