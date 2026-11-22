<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Domain\Tenancy\Actions\CreateBranch;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Admin\Resources\TenantResource;
use Filament\Resources\Pages\CreateRecord;
use VendaPOS\Core\Enums\BusinessType;

/**
 * A new business: its owner, the modules it gets, and its first branch, which
 * runs one of those modules (T7). The tenant's schema is created and migrated
 * when the tenant is saved, so the branch can be opened straight after.
 */
class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->getRecord();
        $name = (string) ($this->data['first_branch_name'] ?? '');
        $type = BusinessType::from((string) ($this->data['first_branch_type'] ?? ''));

        $tenant->run(fn () => app(CreateBranch::class)->handle($name, $type));
    }
}
