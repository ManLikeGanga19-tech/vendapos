<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Gateways\DarajaGateway;
use App\Domain\Payments\Models\PaymentCredential;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use VendaPOS\Core\Enums\MobileMoneyProvider;

#[Signature('payments:credentials {tenant : Tenant ID} {provider : mpesa_ke or airtel_ke} {--environment=sandbox : sandbox or production}')]
#[Description('Store a business’s own mobile-money keys, encrypted. Values are prompted for, never passed as arguments.')]
final class SetPaymentCredentialsCommand extends Command
{
    /** Fields that are typed hidden. */
    private const array SECRETS = ['consumer_secret', 'passkey', 'client_secret'];

    public function handle(): int
    {
        /** @var string $tenantId */
        $tenantId = $this->argument('tenant');
        /** @var string $providerName */
        $providerName = $this->argument('provider');
        /** @var string $environment */
        $environment = $this->option('environment');

        $tenant = Tenant::find($tenantId);
        $provider = MobileMoneyProvider::tryFrom($providerName);

        if (! $tenant instanceof Tenant || $provider === null || ! in_array($environment, [PaymentCredential::SANDBOX, PaymentCredential::PRODUCTION], true)) {
            $this->error('Unknown tenant, provider or environment.');

            return self::FAILURE;
        }

        [$fields, $options] = match ($provider) {
            MobileMoneyProvider::MpesaKe => [DarajaGateway::CREDENTIAL_FIELDS, DarajaGateway::CREDENTIAL_OPTIONS],
            MobileMoneyProvider::AirtelKe => [null, []],
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

            // A wrong value here is only refused by the provider at the first payment, so stop it now.
            if (isset($options[$field]) && ! in_array(trim($value), $options[$field], true)) {
                $this->error("{$field} must be one of: ".implode(', ', $options[$field]).'. Nothing was saved.');

                return self::FAILURE;
            }

            $credentials[$field] = trim($value);
        }

        $tenant->run(fn () => PaymentCredential::query()->updateOrCreate(
            ['provider' => $provider],
            ['environment' => $environment, 'credentials' => $credentials, 'is_active' => true],
        ));

        $this->info("{$provider->value} ({$environment}) credentials saved for {$tenant->id}.");

        return self::SUCCESS;
    }
}
