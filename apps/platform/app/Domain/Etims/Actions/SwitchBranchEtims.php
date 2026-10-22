<?php

declare(strict_types=1);

namespace App\Domain\Etims\Actions;

use App\Domain\Etims\Features\EtimsEnabled;
use App\Domain\Tenancy\Actions\FindEtimsBranchId;
use DomainException;
use Laravel\Pennant\Feature;

/**
 * Turns eTIMS reporting on or off for one branch of the current tenant. A
 * branch can only be switched on once it has its KRA branch ID.
 */
final class SwitchBranchEtims
{
    public function __construct(private readonly FindEtimsBranchId $findEtimsBranchId) {}

    public function handle(string $branchId, bool $on): void
    {
        if ($on && $this->findEtimsBranchId->handle($branchId) === null) {
            throw new DomainException(__('etims.needs_branch_id'));
        }

        $feature = Feature::for(EtimsEnabled::scope($branchId));
        $on ? $feature->activate(EtimsEnabled::class) : $feature->deactivate(EtimsEnabled::class);
    }

    public function isOn(string $branchId): bool
    {
        return Feature::for(EtimsEnabled::scope($branchId))->active(EtimsEnabled::class);
    }
}
