<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tenants:backfill-modules')]
#[Description('Give tenants made before business modules (T7) the modules their branches already run')]
final class BackfillTenantModulesCommand extends Command
{
    public function handle(): int
    {
        $filled = 0;

        Tenant::query()->whereNull('modules')->where('status', '!=', Tenant::PURGED)->each(function (Tenant $tenant) use (&$filled) {
            $types = $tenant->run(fn () => array_values(array_unique(array_column(app(ListBranches::class)->handle(), 'business_type'))));
            $tenant->update(['modules' => $types]);
            $this->line("{$tenant->id}: ".($types === [] ? '(no branches)' : implode(', ', $types)));
            $filled++;
        });

        $this->info("Filled in modules for {$filled} tenant(s).");

        return self::SUCCESS;
    }
}
