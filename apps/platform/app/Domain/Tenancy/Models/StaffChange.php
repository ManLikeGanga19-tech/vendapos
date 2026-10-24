<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One change to a staff member, kept for the owner's audit trail. Never
 * updated; the PIN itself is never recorded.
 *
 * @property int $id
 * @property string $staff_id
 * @property string|null $changed_by staff ID of whoever made the change
 * @property string $action
 * @property array<string, mixed>|null $details
 * @property Carbon $created_at
 */
class StaffChange extends Model
{
    public const ACTION_ADDED = 'added';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_PIN_RESET = 'pin_reset';

    public const ACTION_PIN_CLEARED = 'pin_cleared';

    public const ACTION_DEACTIVATED = 'deactivated';

    public const ACTION_REACTIVATED = 'reactivated';

    public const UPDATED_AT = null;

    protected $fillable = ['staff_id', 'changed_by', 'action', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
