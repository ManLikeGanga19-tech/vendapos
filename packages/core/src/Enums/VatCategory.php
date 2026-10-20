<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

enum VatCategory: string
{
    case Standard = 'standard';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';

    /** Rate in basis points (1600 = 16.00%). */
    public function rate(): int
    {
        return match ($this) {
            self::Standard => 1600,
            self::ZeroRated, self::Exempt => 0,
        };
    }
}
