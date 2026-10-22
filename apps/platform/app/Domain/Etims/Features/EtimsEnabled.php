<?php

declare(strict_types=1);

namespace App\Domain\Etims\Features;

/**
 * Pennant flag, scoped per branch: does this branch report its sales to KRA
 * eTIMS? Off until the branch is registered and certified.
 */
final class EtimsEnabled
{
    public static function scope(string $branchId): string
    {
        return "branch:{$branchId}";
    }

    public function resolve(mixed $scope): bool
    {
        return false;
    }
}
