<?php

declare(strict_types=1);

namespace App\Domain\Etims\Actions;

use App\Domain\Etims\Jobs\SendEtimsDocument;
use App\Domain\Etims\Models\EtimsSubmission;
use DomainException;
use Illuminate\Support\Facades\Log;
use VendaPOS\Core\Enums\EtimsStatus;

/**
 * Makes one pending or failed document due now, for staff in the Admin panel.
 * A failed document gets one more attempt; sending still goes through the
 * normal claim, so this can never send a document twice at once.
 */
final class RetrySubmissionNow
{
    public function handle(string $tenantId, string $submissionId, string $requestedBy): void
    {
        $submission = EtimsSubmission::query()->findOrFail($submissionId);

        if (! in_array($submission->status, [EtimsStatus::Pending, EtimsStatus::Failed], true)) {
            throw new DomainException('Only pending or failed documents can be retried.');
        }

        if ($submission->locked_until?->isFuture()) {
            throw new DomainException('This document is being sent right now.');
        }

        $submission->update([
            'status' => EtimsStatus::Pending,
            'next_attempt_at' => now(),
            // A failed document had used every attempt; this grants exactly one more.
            'attempts' => $submission->status === EtimsStatus::Failed
                ? max(0, (int) config('vendapos.etims.max_attempts') - 1)
                : $submission->attempts,
        ]);

        Log::info('etims.retry_requested', [
            'tenant_id' => $tenantId,
            'submission_id' => $submission->id,
            'order_id' => $submission->order_id,
            'requested_by' => $requestedBy,
        ]);

        SendEtimsDocument::dispatch($tenantId, $submission->id);
    }
}
