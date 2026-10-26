<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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
 * @property string|null $c2b_token where Safaricom confirms money paid straight in (C2B)
 * @property string|null $c2b_status pending, registered or failed
 * @property string|null $c2b_message
 * @property Carbon|null $c2b_registered_at
 */
class PaymentAccount extends Model
{
    public const string SANDBOX = 'sandbox';

    public const string PRODUCTION = 'production';

    public const array ENVIRONMENTS = [self::SANDBOX, self::PRODUCTION];

    public const string C2B_PENDING = 'pending';

    public const string C2B_REGISTERED = 'registered';

    public const string C2B_FAILED = 'failed';

    protected $guarded = [];

    protected $hidden = ['credentials', 'c2b_token'];

    protected function casts(): array
    {
        return [
            'provider' => MobileMoneyProvider::class,
            'credentials' => 'encrypted:array',
            'is_default' => 'boolean',
            'c2b_token' => 'encrypted',
            'c2b_registered_at' => 'datetime',
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

    /** Where Safaricom posts confirmations of money paid straight in; null before the account has a token. */
    public function confirmationUrl(): ?string
    {
        return $this->c2b_token === null ? null : rtrim((string) config('vendapos.payments.callback_base_url'), '/')."/webhooks/c2b/{$this->c2b_token}/confirm";
    }

    /** Only called by Safaricom if the account has external validation switched on; it always accepts. */
    public function validationUrl(): ?string
    {
        return $this->c2b_token === null ? null : rtrim((string) config('vendapos.payments.callback_base_url'), '/')."/webhooks/c2b/{$this->c2b_token}/validate";
    }

    /** "•••• 1234": enough to recognise a key, never enough to use it. */
    public function hint(string $field): ?string
    {
        $value = $this->credentials[$field] ?? null;

        return is_string($value) && $value !== '' ? '•••• '.mb_substr($value, -4) : null;
    }
}
