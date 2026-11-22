<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

use VendaPOS\Core\ValueObjects\Money;

/**
 * The Kenyan notes and coins a cashier counts in a drawer, in shillings.
 * Cents coins are left out: they are not counted at cash-up.
 */
enum KesDenomination: int
{
    case Note1000 = 1000;
    case Note500 = 500;
    case Note200 = 200;
    case Note100 = 100;
    case Note50 = 50;
    case Coin40 = 40;
    case Coin20 = 20;
    case Coin10 = 10;
    case Coin5 = 5;
    case Coin1 = 1;

    public function value(): Money
    {
        return Money::fromCents($this->value * 100);
    }

    public function isNote(): bool
    {
        return $this->value >= 50;
    }

    /** @return list<int> every denomination in shillings, largest first */
    public static function shillings(): array
    {
        return array_map(fn (self $denomination) => $denomination->value, self::cases());
    }
}
