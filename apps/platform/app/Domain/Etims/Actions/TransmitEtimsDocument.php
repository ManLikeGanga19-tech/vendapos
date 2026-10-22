<?php

declare(strict_types=1);

namespace App\Domain\Etims\Actions;

use App\Domain\Etims\Contracts\EtimsClient;
use App\Domain\Etims\Data\EtimsResult;
use App\Domain\Etims\Models\EtimsFailure;
use App\Domain\Etims\Models\EtimsSubmission;
use App\Domain\Orders\Actions\RecordEtimsStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use VendaPOS\Core\Enums\EtimsDocumentType;
use VendaPOS\Core\Enums\EtimsStatus;

/**
 * Sends one due document to eTIMS. The call to KRA happens outside any
 * transaction: a worker claims the submission by locking it for a lease, so
 * two workers never send it at once and a crashed send becomes due again
 * when the lease runs out. The lock is separate from next_attempt_at, so
 * rescheduling a document can never release a send in progress.
 */
final class TransmitEtimsDocument
{
    public function __construct(
        private readonly EtimsClient $client,
        private readonly BuildEtimsDocument $buildDocument,
        private readonly RecordEtimsStatus $recordEtimsStatus,
    ) {}

    public function handle(string $submissionId): void
    {
        if (! $this->claim($submissionId)) {
            return;
        }

        $submission = EtimsSubmission::query()->findOrFail($submissionId);
        $originalReceipt = null;

        if ($submission->document_type === EtimsDocumentType::CreditNote) {
            $sale = EtimsSubmission::query()
                ->where('order_id', $submission->order_id)
                ->where('document_type', EtimsDocumentType::Sale)
                ->first();

            if ($sale?->status === EtimsStatus::NotRequired) {
                $submission->update(['status' => EtimsStatus::NotRequired, 'next_attempt_at' => null, 'locked_until' => null]);

                return;
            }

            if ($sale?->status !== EtimsStatus::Submitted) {
                // The void synced before its sale went through; try again once the sale has.
                $this->recordFailure($submission, EtimsResult::rejected('sale_not_submitted', 'The sale has not been accepted by eTIMS yet.'));

                return;
            }

            $originalReceipt = $sale->receipt;
        }

        try {
            $document = $this->buildDocument->handle($submission, $originalReceipt);

            $result = $submission->document_type === EtimsDocumentType::Sale
                ? $this->client->submitSale($document)
                : $this->client->submitCreditNote($document);
        } catch (Throwable $e) {
            Log::warning('etims.submit_failed', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);

            $result = EtimsResult::rejected('client_error', $e->getMessage());
        }

        $result->accepted
            ? $this->recordAccepted($submission, $result)
            : $this->recordFailure($submission, $result);
    }

    private function claim(string $submissionId): bool
    {
        return EtimsSubmission::query()
            ->whereKey($submissionId)
            ->where('status', EtimsStatus::Pending)
            ->where('next_attempt_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('locked_until')->orWhere('locked_until', '<=', now()))
            ->update(['locked_until' => now()->addSeconds((int) config('vendapos.etims.lease_seconds'))]) === 1;
    }

    private function recordAccepted(EtimsSubmission $submission, EtimsResult $result): void
    {
        DB::transaction(function () use ($submission, $result) {
            $submission->update([
                'status' => EtimsStatus::Submitted,
                'receipt' => $result->receipt,
                'attempts' => $submission->attempts + 1,
                'next_attempt_at' => null,
                'locked_until' => null,
                'submitted_at' => now(),
            ]);

            EtimsFailure::query()
                ->where('submission_id', $submission->id)
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);

            if ($submission->document_type === EtimsDocumentType::Sale) {
                $this->recordEtimsStatus->handle($submission->order_id, EtimsStatus::Submitted, $result->receipt);
            }
        });
    }

    private function recordFailure(EtimsSubmission $submission, EtimsResult $result): void
    {
        DB::transaction(function () use ($submission, $result) {
            $attempt = $submission->attempts + 1;
            $givesUp = $attempt >= (int) config('vendapos.etims.max_attempts');

            $submission->update([
                'status' => $givesUp ? EtimsStatus::Failed : EtimsStatus::Pending,
                'attempts' => $attempt,
                'next_attempt_at' => $givesUp ? null : now()->addSeconds($this->retryDelay($attempt)),
                'locked_until' => null,
            ]);

            EtimsFailure::query()->create([
                'submission_id' => $submission->id,
                'attempt' => $attempt,
                'error_code' => (string) $result->errorCode,
                'error_message' => (string) $result->errorMessage,
                'failed_at' => now(),
            ]);

            if ($givesUp && $submission->document_type === EtimsDocumentType::Sale) {
                $this->recordEtimsStatus->handle($submission->order_id, EtimsStatus::Failed);
            }
        });
    }

    /** 30 s, 60 s, 120 s, then hourly (architecture §6.1). */
    private function retryDelay(int $attempt): int
    {
        /** @var list<int> $delays */
        $delays = config('vendapos.etims.retry_delays_seconds');

        return $delays[$attempt - 1] ?? (int) config('vendapos.etims.retry_every_seconds');
    }
}
