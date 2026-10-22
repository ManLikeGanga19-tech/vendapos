<?php

declare(strict_types=1);

namespace App\Domain\Etims\Jobs;

use App\Domain\Etims\Actions\QueueEtimsDocument;
use App\Domain\Orders\Actions\RecordEtimsStatus;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;
use VendaPOS\Core\Enums\EtimsDocumentType;
use VendaPOS\Core\Enums\EtimsStatus;

/** order.recorded → the sale document KRA is owed (architecture §9). */
final class SubmitSaleToEtims extends TenantEventJob
{
    protected static function queueName(): string
    {
        return 'etims';
    }

    protected function process(DomainEvent $event): void
    {
        // Cancelled before completion: no receipt was issued, so KRA is owed nothing.
        if (($event->payload['status'] ?? null) === 'cancelled') {
            app(RecordEtimsStatus::class)->handle((string) $event->aggregate_id, EtimsStatus::NotRequired);

            return;
        }

        $queue = app(QueueEtimsDocument::class);

        $queue->handle(EtimsDocumentType::Sale, $event);

        // Rung up and voided while offline, so no order.voided will follow: reverse it here.
        if (($event->payload['status'] ?? null) === 'voided') {
            $queue->handle(EtimsDocumentType::CreditNote, $event);
        }
    }
}
