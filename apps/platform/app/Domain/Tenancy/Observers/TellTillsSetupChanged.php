<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Observers;

use App\Domain\Tenancy\Actions\NotifyTills;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Realtime\TillsShouldSync;

/**
 * The till itself, its branch and its business. Devices are touched on every
 * API request (last_seen_at), so only the fields a till shows or acts on
 * count.
 */
final class TellTillsSetupChanged
{
    public function __construct(private readonly NotifyTills $notifyTills) {}

    public function updated(Device|Branch|Tenant $model): void
    {
        match (true) {
            $model instanceof Device && $model->wasChanged(['revoked_at', 'name', 'branch_id']) => $this->notifyTills->device($model->id),
            $model instanceof Branch && $model->wasChanged(['name', 'business_type']) && is_string(tenant('id')) => $this->notifyTills->ofTenant((string) tenant('id'), TillsShouldSync::DEVICE, [$model->id]),
            $model instanceof Tenant && $model->wasChanged(['status', 'name', 'data']) => $this->notifyTills->ofTenant((string) $model->getTenantKey(), TillsShouldSync::ACCOUNT),
            default => null,
        };
    }
}
