<?php

declare(strict_types=1);

namespace App\Domain\Etims\Actions;

use App\Domain\Etims\Models\EtimsSubmission;
use VendaPOS\Core\Enums\EtimsStatus;

/** How many of the current tenant's eTIMS documents are waiting, and how many have failed. */
final class CountOpenSubmissions
{
    /**
     * @param  list<string>|null  $branchIds  null for every branch
     * @return array{pending: int, failed: int}
     */
    public function handle(?array $branchIds = null): array
    {
        $counts = EtimsSubmission::query()
            ->whereIn('status', [EtimsStatus::Pending, EtimsStatus::Failed])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($counts[EtimsStatus::Pending->value] ?? 0),
            'failed' => (int) ($counts[EtimsStatus::Failed->value] ?? 0),
        ];
    }
}
