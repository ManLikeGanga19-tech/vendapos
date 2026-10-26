<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Events\PaymentAccountChanged;
use App\Domain\Payments\Exceptions\PaymentAccountRefused;
use App\Domain\Payments\Gateways\DarajaGateway;
use App\Domain\Payments\Gateways\PaymentGateways;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Payments\Models\PaymentAccountChange;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;
use VendaPOS\Core\Enums\MobileMoneyProvider;

/**
 * Adds or changes a payment account. Keys are tried with the provider before
 * they are saved; a blank key keeps the saved one. Every account of a
 * business uses one environment, so test money never mixes with real money
 * (Daniel, T3b). Who may call this is checked by the caller: owners only.
 */
final class SavePaymentAccount
{
    public function __construct(private readonly PaymentGateways $gateways) {}

    /**
     * @param  array<string, mixed>  $credentials
     *
     * @throws PaymentAccountRefused
     */
    public function handle(
        ?int $accountId,
        string $name,
        MobileMoneyProvider $provider,
        string $environment,
        #[SensitiveParameter] array $credentials,
        bool $isDefault,
        ?string $changedBy,
    ): PaymentAccount {
        [$fields, $options] = match ($provider) {
            MobileMoneyProvider::MpesaKe => [DarajaGateway::CREDENTIAL_FIELDS, DarajaGateway::CREDENTIAL_OPTIONS],
            MobileMoneyProvider::AirtelKe => throw new PaymentAccountRefused('unsupported_provider'),
        };

        $account = $accountId !== null ? PaymentAccount::query()->find($accountId) : null;

        if ($accountId !== null && ($account === null || $account->provider !== $provider)) {
            throw new PaymentAccountRefused('not_found');
        }

        $otherEnvironment = PaymentAccount::query()
            ->when($account !== null, fn ($query) => $query->whereKeyNot($accountId))
            ->value('environment');

        if (! in_array($environment, PaymentAccount::ENVIRONMENTS, true) || ($otherEnvironment !== null && $otherEnvironment !== $environment)) {
            throw new PaymentAccountRefused('environment_mixed');
        }

        if ($account !== null && $account->is_default && ! $isDefault) {
            throw new PaymentAccountRefused('default_required');
        }

        $saved = $account !== null ? $account->credentials : [];
        $merged = [];

        foreach ($fields as $field) {
            $value = trim(is_string($credentials[$field] ?? null) ? $credentials[$field] : '');
            $merged[$field] = $value !== '' ? $value : (string) ($saved[$field] ?? '');

            if ($merged[$field] === '') {
                throw new PaymentAccountRefused('missing_field', "{$field} is required.");
            }

            // A wrong value here is only refused by the provider at the first payment, so stop it now.
            if (isset($options[$field]) && ! in_array($merged[$field], $options[$field], true)) {
                throw new PaymentAccountRefused('invalid_option', "{$field} must be one of: ".implode(', ', $options[$field]).'.');
            }
        }

        $changedFields = array_keys(array_filter($merged, fn (string $value, string $field) => ($saved[$field] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));
        $environmentChanged = $account !== null && $account->environment !== $environment;

        // Renaming needs no call to the provider; new keys or a new environment do.
        if ($account === null || $changedFields !== [] || $environmentChanged) {
            $problem = $this->gateways->for($provider)->checkCredentials($merged, $environment);

            if ($problem !== null) {
                throw new PaymentAccountRefused($problem);
            }
        }

        return DB::transaction(function () use ($account, $name, $provider, $environment, $merged, $isDefault, $changedBy, $changedFields, $environmentChanged) {
            $currentDefault = PaymentAccount::query()->where('provider', $provider)->where('is_default', true)->lockForUpdate()->first();

            // The first account is the default; the default stays one until another takes over.
            $makeDefault = $isDefault || $currentDefault === null || $currentDefault->id === $account?->id;

            if ($makeDefault && $currentDefault !== null && $currentDefault->id !== $account?->id) {
                $currentDefault->update(['is_default' => false]);
            }

            $wasDefault = $account !== null && $account->is_default;
            $oldName = $account?->name;
            $account ??= new PaymentAccount(['provider' => $provider]);
            $account->fill(['name' => $name, 'environment' => $environment, 'credentials' => $merged, 'is_default' => $makeDefault])->save();

            $action = $account->wasRecentlyCreated ? PaymentAccountChange::ACTION_ADDED : PaymentAccountChange::ACTION_UPDATED;

            // Field names only: the audit trail never holds a key.
            PaymentAccountChange::create([
                'payment_account_id' => $account->id,
                'changed_by' => $changedBy,
                'action' => $action,
                'details' => array_filter([
                    'fields' => $action === PaymentAccountChange::ACTION_UPDATED ? $changedFields : [],
                    'name' => $oldName !== null && $oldName !== $name ? ['from' => $oldName, 'to' => $name] : [],
                    'environment' => $action === PaymentAccountChange::ACTION_ADDED || $environmentChanged ? $environment : null,
                    'made_default' => $makeDefault && ! $wasDefault ? true : null,
                ], fn ($value) => $value !== null && $value !== []),
            ]);

            PaymentAccountChanged::dispatch($account->id, $account->name, $action, $changedBy);

            return $account;
        });
    }
}
