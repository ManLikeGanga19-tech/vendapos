<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central map from a C2B confirmation URL token's SHA-256 to one tenant's
 * payment account. Confirmations carry no tenant; this is how they find one.
 *
 * @property string $token_hash
 * @property string $tenant_id
 * @property int $payment_account_id
 */
class C2bRoute extends Model
{
    use CentralConnection;

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'token_hash';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function forToken(string $token): ?self
    {
        return self::query()->whereKey(hash('sha256', $token))->first();
    }
}
