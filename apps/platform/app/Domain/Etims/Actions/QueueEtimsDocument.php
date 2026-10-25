<?php

declare(strict_types=1);

namespace App\Domain\Etims\Actions;

use App\Domain\Etims\Features\EtimsEnabled;
use App\Domain\Etims\Jobs\SendEtimsDocument;
use App\Domain\Etims\Models\EtimsSubmission;
use App\Domain\Orders\Actions\RecordEtimsStatus;
use App\Infrastructure\Outbox\DomainEvent;
use Laravel\Pennant\Feature;
use LogicException;
use VendaPOS\Core\Enums\EtimsDocumentType;
use VendaPOS\Core\Enums\EtimsStatus;

/**
 * Records the document an order event owes KRA. Every order gets one, so the
 * order always has an eTIMS status: "not required" while the branch has
 * eTIMS off, otherwise "pending" until the document is sent after commit.
 */
final class QueueEtimsDocument
{
    public function __construct(private readonly RecordEtimsStatus $recordEtimsStatus) {}

    public function handle(EtimsDocumentType $type, DomainEvent $event): EtimsSubmission
    {
        if ($event->branch_id === null || $event->aggregate_id === null || $event->device_id === null) {
            throw new LogicException("Order event {$event->event_id} has no branch, order or device.");
        }

        $submission = EtimsSubmission::query()->firstOrCreate(
            ['order_id' => $event->aggregate_id, 'document_type' => $type],
            [
                'branch_id' => $event->branch_id,
                'status' => $status = $this->statusFor($type, $event->aggregate_id, $event->branch_id),
                'order_snapshot' => [...$event->payload, 'branch_id' => $event->branch_id, 'device_id' => $event->device_id],
                // Due at once; the job sent after commit claims it, and etims:retry picks it up if that job is lost.
                'next_attempt_at' => $status === EtimsStatus::Pending ? now() : null,
            ],
        );

        if ($type === EtimsDocumentType::Sale) {
            $this->recordEtimsStatus->handle($submission->order_id, $submission->status);
        }

        if ($submission->wasRecentlyCreated && $submission->status === EtimsStatus::Pending) {
            SendEtimsDocument::dispatch((string) $event->tenant_id, $submission->id)->afterCommit();
        }

        return $submission;
    }

    private function statusFor(EtimsDocumentType $type, string $orderId, string $branchId): EtimsStatus
    {
        // A credit note follows its sale: nothing to reverse if the sale was never reported.
        if ($type === EtimsDocumentType::CreditNote) {
            $sale = EtimsSubmission::query()
                ->where('order_id', $orderId)
                ->where('document_type', EtimsDocumentType::Sale)
                ->value('status');

            if ($sale === EtimsStatus::NotRequired->value) {
                return EtimsStatus::NotRequired;
            }
        }

        return Feature::for(EtimsEnabled::scope($branchId))->active(EtimsEnabled::class)
            ? EtimsStatus::Pending
            : EtimsStatus::NotRequired;
    }
}
