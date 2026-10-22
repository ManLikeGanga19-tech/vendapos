<?php

declare(strict_types=1);

namespace App\Domain\Etims\Clients;

use App\Domain\Etims\Contracts\EtimsClient;
use App\Domain\Etims\Data\EtimsResult;
use VendaPOS\Core\Data\EtimsDocumentData;

/** Accepts every document with a made-up receipt (tests, demos, local work). Never use in production. */
final class FakeEtimsClient extends EtimsClient
{
    public function submitSale(EtimsDocumentData $document): EtimsResult
    {
        return $this->receipt('FAKE', $document);
    }

    public function submitCreditNote(EtimsDocumentData $document): EtimsResult
    {
        return $this->receipt('FAKE-CN', $document);
    }

    private function receipt(string $prefix, EtimsDocumentData $document): EtimsResult
    {
        $invoice = "{$prefix}-{$document->sequence_number}";

        return EtimsResult::accepted([
            'fdn' => $invoice,
            'invoice_id' => $invoice,
            'qr' => 'https://example.invalid/etims/'.$document->order_id,
        ]);
    }
}
