<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One change to a payment account, kept for the owner's audit trail. Never
 * updated; key values are never recorded, only which fields changed.
 *
 * @property int $id
 * @property int $payment_account_id
 * @property string|null $changed_by staff ID of whoever made the change
 * @property string $action
 * @property array<string, mixed>|null $details
 * @property Carbon $created_at
 */
class PaymentAccountChange extends Model
{
    public const string ACTION_ADDED = 'added';

    public const string ACTION_UPDATED = 'updated';

    public const string ACTION_BRANCHES_CHANGED = 'branches_changed';

    public const UPDATED_AT = null;

    protected $fillable = ['payment_account_id', 'changed_by', 'action', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
