<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use VendaPOS\Core\Enums\MobileMoneyProvider;

/**
 * One paybill or till of the business, with its own keys encrypted at rest.
 * A branch uses the account assigned to it (payment_account_branches), or
 * the provider's default account.
 *
 * @property int $id
 * @property string $name
 * @property MobileMoneyProvider $provider
 * @property string $environment
 * @property array<string, string> $credentials
 * @property bool $is_default
 */
class PaymentAccount extends Model
{
    public const string SANDBOX = 'sandbox';

    public const string PRODUCTION = 'production';

    public const array ENVIRONMENTS = [self::SANDBOX, self::PRODUCTION];

    protected $guarded = [];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'provider' => MobileMoneyProvider::class,
            'credentials' => 'encrypted:array',
            'is_default' => 'boolean',
        ];
    }

    /** The account a branch takes this provider's payments into, if any. */
    public static function forBranch(MobileMoneyProvider $provider, string $branchId): ?self
    {
        $assigned = self::query()
            ->whereIn('id', fn ($query) => $query->select('payment_account_id')->from('payment_account_branches')
                ->where('branch_id', $branchId)->where('provider', $provider->value))
            ->first();

        return $assigned ?? self::defaultFor($provider);
    }

    public static function defaultFor(MobileMoneyProvider $provider): ?self
    {
        return self::query()->where('provider', $provider)->where('is_default', true)->first();
    }

    /** The environment every account of this business uses, or null before the first one. */
    public static function businessEnvironment(): ?string
    {
        $environment = self::query()->value('environment');

        return is_string($environment) ? $environment : null;
    }

    /** "•••• 1234": enough to recognise a key, never enough to use it. */
    public function hint(string $field): ?string
    {
        $value = $this->credentials[$field] ?? null;

        return is_string($value) && $value !== '' ? '•••• '.mb_substr($value, -4) : null;
    }
}
