<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/** State of a sale as recorded by the terminal. Last write wins on sync (architecture §12). */
enum OrderStatus: string
{
    case Completed = 'completed';
    case Voided = 'voided';
}
