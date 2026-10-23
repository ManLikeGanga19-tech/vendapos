<?php

declare(strict_types=1);

namespace App\Domain\Etims\Actions;

use App\Domain\Etims\Models\EtimsFailure;
use App\Domain\Etims\Models\EtimsSubmission;
use VendaPOS\Core\Enums\EtimsStatus;

/** The current tenant's eTIMS documents that still need sending or attention, oldest first. */
final class ListOpenSubmissions
{
    /**
     * @param  list<string>|null  $branchIds  null for every branch
     * @return array<string, array{id: string, order_id: string, branch_id: string, document_type: string, status: string,
     *     attempts: int, next_attempt_at: string|null, last_error: string|null, created_at: string}> keyed by submission ID
     */
    public function handle(int $limit = 200, ?array $branchIds = null): array
    {
        $submissions = EtimsSubmission::query()
            ->whereIn('status', [EtimsStatus::Pending, EtimsStatus::Failed])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $lastErrors = EtimsFailure::query()
            ->whereIn('submission_id', $submissions->modelKeys())
            ->orderBy('id')
            ->get(['submission_id', 'error_code', 'error_message'])
            ->keyBy('submission_id');

        return $submissions->mapWithKeys(function (EtimsSubmission $s) use ($lastErrors) {
            $error = $lastErrors->get($s->id);

            return [$s->id => [
                'id' => $s->id,
                'order_id' => $s->order_id,
                'branch_id' => $s->branch_id,
                'document_type' => $s->document_type->value,
                'status' => $s->status->value,
                'attempts' => $s->attempts,
                'next_attempt_at' => $s->next_attempt_at?->toIso8601String(),
                'last_error' => $error instanceof EtimsFailure ? "{$error->error_code}: {$error->error_message}" : null,
                'created_at' => $s->created_at?->toIso8601String() ?? '',
            ]];
        })->all();
    }
}
