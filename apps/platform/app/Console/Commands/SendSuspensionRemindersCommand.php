<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantStatusChange;
use App\Domain\Tenancy\Notifications\TenantPurgeReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('tenants:suspension-reminders')]
#[Description('Remind owners of suspended tenants when 7 days and 1 day are left before deletion')]
final class SendSuspensionRemindersCommand extends Command
{
    /** Days before the purge date (Africa/Nairobi calendar days) on which the owner is reminded. */
    public const array REMIND_AT_DAYS_LEFT = [7, 1];

    public function handle(): int
    {
        $timezone = (string) config('vendapos.business_timezone');
        $today = now($timezone)->startOfDay();
        $sent = 0;

        Tenant::query()->where('status', Tenant::SUSPENDED)->whereNotNull('purge_after')->each(function (Tenant $tenant) use ($today, $timezone, &$sent) {
            $purgeDay = $tenant->purge_after?->timezone($timezone)->startOfDay();
            $daysLeft = $purgeDay === null ? null : (int) $today->diffInDays($purgeDay, false);

            if (! in_array($daysLeft, self::REMIND_AT_DAYS_LEFT, true)) {
                return;
            }

            DB::connection($tenant->getConnectionName())->transaction(function () use ($tenant, $daysLeft, &$sent) {
                // The tenant row lock serialises overlapping runs; then once per reminder per suspension.
                $locked = Tenant::query()->whereKey($tenant->getTenantKey())->lockForUpdate()->first();

                $already = TenantStatusChange::query()
                    ->where('tenant_id', $tenant->getTenantKey())
                    ->where('action', TenantStatusChange::REMINDED)
                    ->where('created_at', '>=', $tenant->suspended_at)
                    ->where('details->days_left', $daysLeft)
                    ->exists();

                if ($already || ! $locked?->isSuspended()) {
                    return;
                }

                TenantStatusChange::query()->create([
                    'tenant_id' => $tenant->getTenantKey(),
                    'action' => TenantStatusChange::REMINDED,
                    'details' => ['days_left' => $daysLeft],
                ]);

                $tenant->notify(new TenantPurgeReminder($daysLeft, $tenant->purge_after->toImmutable()));
                $sent++;
            });
        });

        $this->info("Sent {$sent} suspension reminders.");

        return self::SUCCESS;
    }
}
