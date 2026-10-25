<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Exceptions;

use RuntimeException;

/** A menu change that would clash with what is already there. $reason is one of the constants. */
final class MenuConflict extends RuntimeException
{
    public const SKU_TAKEN = 'sku_taken';

    public const BARCODE_TAKEN = 'barcode_taken';

    public const CATEGORY_NAME_TAKEN = 'category_name_taken';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Menu conflict: {$reason}.");
    }
}
