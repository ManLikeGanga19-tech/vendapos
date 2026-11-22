<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Observers;

use App\Domain\Tenancy\Actions\NotifyTills;
use App\Domain\Verticals\Models\DiningTable;
use App\Domain\Verticals\Models\FloorArea;
use App\Infrastructure\Realtime\TillsShouldSync;

/** An area or table changed: that branch's tills fetch the floor plan now. */
final class TellTillsFloorChanged
{
    public function __construct(private readonly NotifyTills $notifyTills) {}

    public function saved(FloorArea|DiningTable $model): void
    {
        $this->notify($model->branch_id);
    }

    public function deleted(FloorArea|DiningTable $model): void
    {
        $this->notify($model->branch_id);
    }

    private function notify(string $branchId): void
    {
        $tenantId = tenant('id');

        if (is_string($tenantId)) {
            $this->notifyTills->ofTenant($tenantId, TillsShouldSync::TABLES, [$branchId]);
        }
    }
}
