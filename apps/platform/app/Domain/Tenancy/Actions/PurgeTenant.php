<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Exceptions\TenantStatusConflict;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantExport;
use App\Domain\Tenancy\Models\TenantStatusChange;
use App\Domain\Tenancy\Notifications\TenantPurged;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Permanently deletes a suspended tenant's data once its purge date has
 * passed. First a fresh export is taken and verified (the tills kept
 * syncing while suspended, so an older export would miss sales); without a
 * verified export nothing is deleted. Then, in one central transaction, the
 * tenant schema is dropped and the tenant's rows in central tables are
 * removed, so a purge either happens completely or not at all. The tenant
 * row stays as a tombstone, with the owner's contact and the audit trail,
 * and the owner is told where the export is kept.
 */
final class PurgeTenant
{
    public function __construct(private readonly ExportTenantSchema $export) {}

    public function handle(Tenant $tenant): TenantExport
    {
        $this->ensurePurgeable($tenant);

        $branchIds = $tenant->run(fn () => Branch::query()->pluck('id')->all());
        $export = $this->export->handle($tenant, TenantExport::FOR_PURGE);

        $central = DB::connection($tenant->getConnectionName());

        $central->transaction(function () use ($central, $tenant, $branchIds, $export) {
            $locked = Tenant::query()->whereKey($tenant->getTenantKey())->lockForUpdate()->firstOrFail();

            // Reactivated while the export ran: keep everything (the export stays, harmless).
            $this->ensurePurgeable($locked);

            $tenantId = (string) $locked->getTenantKey();

            // The one way past domain_events' append-only trigger; ends with this transaction.
            $central->select("SELECT set_config('vendapos.purge_tenant', ?, true)", [$tenantId]);

            $deviceIds = Device::query()->where('tenant_id', $tenantId)->pluck('id')->all();
            $central->table('personal_access_tokens')
                ->where('tokenable_type', (new Device)->getMorphClass())
                ->whereIn('tokenable_id', $deviceIds)
                ->delete();
            Device::query()->where('tenant_id', $tenantId)->delete();

            foreach (['payment_callback_routes', 'webhook_receipts', 'domain_events'] as $table) {
                $central->table($table)->where('tenant_id', $tenantId)->delete();
            }

            $central->table('features')->whereIn('scope', array_map(fn (string $id) => "branch:{$id}", $branchIds))->delete();
            $locked->domains()->delete();

            $central->statement('DROP SCHEMA '.$central->getQueryGrammar()->wrap((string) $locked->database()->getName()).' CASCADE');

            $locked->forceFill(['status' => Tenant::PURGED])->save();

            TenantStatusChange::query()->create([
                'tenant_id' => $tenantId,
                'action' => TenantStatusChange::PURGED,
                'details' => ['export_id' => $export->id, 'sha256' => $export->sha256, 'table_counts' => $export->table_counts],
            ]);

            $locked->notify(new TenantPurged($export->expires_at?->toImmutable() ?? now()->toImmutable()));
        });

        $this->forgetTenantCache((string) $tenant->getTenantKey());

        Log::channel('security')->warning('tenancy.tenant_purged', ['tenant_id' => $tenant->getTenantKey(), 'export_id' => $export->id]);

        return $export;
    }

    private function ensurePurgeable(Tenant $tenant): void
    {
        if (! $tenant->isSuspended() || $tenant->purge_after === null || $tenant->purge_after->isFuture()) {
            throw new TenantStatusConflict("Tenant [{$tenant->getTenantKey()}] is not due for purging.");
        }
    }

    /** Cached values the tenant left behind (its cache calls are tagged per tenant). */
    private function forgetTenantCache(string $tenantId): void
    {
        $store = Cache::store();

        if ($store->getStore() instanceof TaggableStore) {
            $store->tags([config('tenancy.cache.tag_base').$tenantId])->flush();
        }
    }
}
