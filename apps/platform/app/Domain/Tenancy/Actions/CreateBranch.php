<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Validation\ValidationException;
use VendaPOS\Core\Enums\BusinessType;

/**
 * Opens a branch of the current tenant. Its business type must be one of the
 * tenant's modules and a module that is built (T7): the type decides the
 * till's screens and the owner panel's pages for that branch.
 */
final class CreateBranch
{
    public const int NAME_MAX = 120;

    /** @throws ValidationException */
    public function handle(string $name, BusinessType $type): Branch
    {
        $tenant = tenant();
        $name = trim($name);

        if (! $tenant instanceof Tenant) {
            throw new \LogicException('A branch is opened inside a tenant.');
        }

        if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
            throw ValidationException::withMessages(['name' => __('validation.required', ['attribute' => __('owner.branches.name')])]);
        }

        if (! $type->isAvailable() || ! $tenant->hasModule($type)) {
            throw ValidationException::withMessages(['business_type' => __('owner.branches.type_not_allowed')]);
        }

        if (Branch::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            throw ValidationException::withMessages(['name' => __('owner.branches.name_taken')]);
        }

        return Branch::create(['name' => $name, 'business_type' => $type]);
    }
}
