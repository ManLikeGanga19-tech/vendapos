<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Jobs\RegisterConfirmationUrls;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:register-c2b {tenant : Tenant ID}')]
#[Description('Ask Safaricom to confirm money paid straight into each of a business’s accounts (C2B), for accounts saved before this existed.')]
final class RegisterC2bUrlsCommand extends Command
{
    public function handle(): int
    {
        /** @var string $tenantId */
        $tenantId = $this->argument('tenant');
        $tenant = Tenant::find($tenantId);

        if (! $tenant instanceof Tenant) {
            $this->error('Unknown tenant.');

            return self::FAILURE;
        }

        $ids = $tenant->run(fn () => PaymentAccount::query()->pluck('id')->all());

        foreach ($ids as $id) {
            RegisterConfirmationUrls::dispatch((string) $tenant->getTenantKey(), (int) $id);
        }

        $this->info(count($ids).' account(s) queued for C2B registration.');

        return self::SUCCESS;
    }
}
