<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central map from a callback URL token's SHA-256 to one tenant's payment.
 * Callbacks carry no tenant; this is the only way they find one.
 *
 * @property string $token_hash
 * @property string $tenant_id
 * @property string $payment_id
 * @property string $provider
 */
class PaymentCallbackRoute extends Model
{
    use CentralConnection;

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'token_hash';

    protected $keyType = 'string';

    public static function forToken(string $token): ?self
    {
        return self::query()->whereKey(hash('sha256', $token))->first();
    }
}
