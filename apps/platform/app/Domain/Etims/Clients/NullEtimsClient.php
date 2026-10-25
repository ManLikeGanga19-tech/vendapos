<?php

declare(strict_types=1);

namespace App\Domain\Etims\Clients;

use App\Domain\Etims\Contracts\EtimsClient;
use App\Domain\Etims\Data\EtimsResult;
use VendaPOS\Core\Data\EtimsDocumentData;

/**
 * The default until a certified client exists. Branches with eTIMS off never
 * reach a client; if a branch is switched on early, its documents fail and
 * wait for retry rather than being silently marked as sent.
 */
final class NullEtimsClient extends EtimsClient
{
    public function submitSale(EtimsDocumentData $document): EtimsResult
    {
        return $this->unavailable();
    }

    public function submitCreditNote(EtimsDocumentData $document): EtimsResult
    {
        return $this->unavailable();
    }

    private function unavailable(): EtimsResult
    {
        return EtimsResult::rejected('no_client', 'No eTIMS client is configured.');
    }
}
