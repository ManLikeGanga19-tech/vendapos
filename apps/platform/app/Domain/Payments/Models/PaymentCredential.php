<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use VendaPOS\Core\Enums\MobileMoneyProvider;

/**
 * A business's own keys for one provider, encrypted at rest.
 *
 * @property int $id
 * @property MobileMoneyProvider $provider
 * @property string $environment
 * @property array<string, string> $credentials
 * @property bool $is_active
 */
class PaymentCredential extends Model
{
    public const string SANDBOX = 'sandbox';

    public const string PRODUCTION = 'production';

    protected $guarded = [];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'provider' => MobileMoneyProvider::class,
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public static function activeFor(MobileMoneyProvider $provider): ?self
    {
        return self::query()->where('provider', $provider)->where('is_active', true)->first();
    }
}
