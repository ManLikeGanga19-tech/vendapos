<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Actions\SavePaymentAccount;
use App\Domain\Payments\Exceptions\PaymentAccountRefused;
use App\Domain\Payments\Gateways\DarajaGateway;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use VendaPOS\Core\Enums\MobileMoneyProvider;

#[Signature('payments:credentials {tenant : Tenant ID} {provider : mpesa_ke or airtel_ke} {--environment=sandbox : sandbox or production} {--name=Default : Account name, for a new default account}')]
#[Description('Store a business’s keys in its default payment account, encrypted. Values are prompted for, never passed as arguments.')]
final class SetPaymentCredentialsCommand extends Command
{
    /** Fields that are typed hidden. */
    private const array SECRETS = ['consumer_secret', 'passkey', 'client_secret'];

    public function handle(SavePaymentAccount $save): int
    {
        /** @var string $tenantId */
        $tenantId = $this->argument('tenant');
        /** @var string $providerName */
        $providerName = $this->argument('provider');
        /** @var string $environment */
        $environment = $this->option('environment');
        /** @var string $name */
        $name = $this->option('name');

        $tenant = Tenant::find($tenantId);
        $provider = MobileMoneyProvider::tryFrom($providerName);

        if (! $tenant instanceof Tenant || $provider === null || ! in_array($environment, PaymentAccount::ENVIRONMENTS, true)) {
            $this->error('Unknown tenant, provider or environment.');

            return self::FAILURE;
        }

        $fields = match ($provider) {
            MobileMoneyProvider::MpesaKe => DarajaGateway::CREDENTIAL_FIELDS,
            MobileMoneyProvider::AirtelKe => null,
        };

        if ($fields === null) {
            $this->error("{$provider->value} is not supported yet.");

            return self::FAILURE;
        }

        $credentials = [];

        foreach ($fields as $field) {
            $value = in_array($field, self::SECRETS, true) ? $this->secret($field) : $this->ask($field);

            if (! is_string($value) || trim($value) === '') {
                $this->error("{$field} is required.");

                return self::FAILURE;
            }

            $credentials[$field] = trim($value);
        }

        try {
            $tenant->run(function () use ($save, $provider, $environment, $name, $credentials) {
                $default = PaymentAccount::defaultFor($provider);

                $save->handle($default?->id, $default->name ?? $name, $provider, $environment, $credentials, isDefault: true, changedBy: null);
            });
        } catch (PaymentAccountRefused $e) {
            $this->error("{$e->getMessage()} Nothing was saved.");

            return self::FAILURE;
        }

        $this->info("{$provider->value} ({$environment}) default account saved for {$tenant->id}.");

        return self::SUCCESS;
    }
}
