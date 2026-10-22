<?php

declare(strict_types=1);

namespace App\Domain\Etims\Contracts;

use App\Domain\Etims\Data\EtimsResult;
use VendaPOS\Core\Data\EtimsDocumentData;

/**
 * Talks to KRA eTIMS (OSCU online, VSCU for offline batches). The real
 * client maps EtimsDocumentData to KRA's fields once the current spec is
 * confirmed (architecture §9). A client may throw on transport errors;
 * callers count that as a failed attempt and retry. An abstract class
 * rather than an interface because everything under App\Domain must be a
 * class (arch test).
 */
abstract class EtimsClient
{
    abstract public function submitSale(EtimsDocumentData $document): EtimsResult;

    /** $document->original_receipt holds what KRA returned for the sale being reversed. */
    abstract public function submitCreditNote(EtimsDocumentData $document): EtimsResult;
}
