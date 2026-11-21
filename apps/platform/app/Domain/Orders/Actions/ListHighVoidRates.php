<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\VoidCheck;

/** The current tenant's high void-rate flags, newest day first, for the Admin panel (architecture §10). */
final class ListHighVoidRates
{
    /** @return list<array{id: int, branch_id: string|null, staff_id: string|null, business_date: string|null, sales: int, voided: int, percent: int, status: string}> */
    public function handle(int $limit = 200): array
    {
        return array_values(VoidCheck::query()
            ->where('kind', VoidCheck::HIGH_VOID_RATE)
            ->orderByDesc('business_date')->orderByDesc('id')
            ->limit($limit)->get()
            ->map(fn (VoidCheck $check) => [
                'id' => $check->id,
                'branch_id' => $check->branch_id,
                'staff_id' => $check->staff_id,
                'business_date' => $check->business_date?->toDateString(),
                'sales' => (int) ($check->details['sales'] ?? 0),
                'voided' => (int) ($check->details['voided'] ?? 0),
                'percent' => (int) ($check->details['percent'] ?? 0),
                'status' => $check->status,
            ])
            ->all());
    }
}
