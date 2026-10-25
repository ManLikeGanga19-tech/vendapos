<?php

declare(strict_types=1);

namespace App\Domain\Etims\Jobs;

use App\Domain\Etims\Actions\QueueEtimsDocument;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;
use VendaPOS\Core\Enums\EtimsDocumentType;

/**
 * order.voided → a credit note reversing the sale. A seam for now: KRA's
 * exact refund and void rules get mapped in the real client.
 */
final class SubmitCreditNoteToEtims extends TenantEventJob
{
    protected static function queueName(): string
    {
        return 'etims';
    }

    protected function process(DomainEvent $event): void
    {
        app(QueueEtimsDocument::class)->handle(EtimsDocumentType::CreditNote, $event);
    }
}
