<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Enums\BusinessType;

/**
 * A branch of a tenant's business, stored in the tenant's own schema.
 * Its business type selects the vertical pack the branch runs.
 *
 * @property string $id
 * @property string $name
 * @property BusinessType $business_type
 * @property string|null $etims_branch_id
 * @property Carbon $created_at
 */
class Branch extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'business_type', 'etims_branch_id'];

    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
        ];
    }
}
