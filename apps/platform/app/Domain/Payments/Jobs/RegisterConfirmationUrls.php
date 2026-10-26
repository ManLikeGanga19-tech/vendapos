<?php

declare(strict_types=1);

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Gateways\PaymentGateways;
use App\Domain\Payments\Models\PaymentAccount;
use App\Infrastructure\Queue\LiveTenants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gives an account its C2B confirmation URL (a secret token, routed centrally)
 * and registers it with the provider. A refusal is recorded for the owner to
 * see; an unreachable provider is retried.
 */
final class RegisterConfirmationUrls implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $accountId,
    ) {
        $this->onQueue('payments');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(PaymentGateways $gateways): void
    {
        if (! LiveTenants::has($this->tenantId)) {
            return;
        }

        $previous = tenant();
        tenancy()->initialize($this->tenantId);

        try {
            $account = PaymentAccount::query()->find($this->accountId);

            if ($account === null) {
                return;
            }

            $this->ensureToken($account);
            $account->update(['c2b_status' => PaymentAccount::C2B_PENDING, 'c2b_message' => null]);

            $problem = $gateways->for($account->provider)->registerConfirmationUrls($account);

            $account->update($problem === null
                ? ['c2b_status' => PaymentAccount::C2B_REGISTERED, 'c2b_message' => null, 'c2b_registered_at' => now()]
                : ['c2b_status' => PaymentAccount::C2B_FAILED, 'c2b_message' => Str::limit($problem, 250)]);
        } finally {
            $previous === null ? tenancy()->end() : tenancy()->initialize($previous);
        }
    }

    public function failed(?Throwable $e): void
    {
        if (! LiveTenants::has($this->tenantId)) {
            return;
        }

        tenancy()->initialize($this->tenantId);

        try {
            PaymentAccount::query()->whereKey($this->accountId)
                ->update(['c2b_status' => PaymentAccount::C2B_FAILED, 'c2b_message' => 'Safaricom could not be reached. Try again later.']);
        } finally {
            tenancy()->end();
        }
    }

    /** The token and its central route commit together. */
    private function ensureToken(PaymentAccount $account): void
    {
        if ($account->c2b_token !== null) {
            return;
        }

        DB::transaction(function () use ($account) {
            $token = Str::random(48);
            $account->update(['c2b_token' => $token]);

            // Central table, written on the tenant connection so it commits with the token.
            DB::table('public.c2b_routes')->insert([
                'token_hash' => hash('sha256', $token),
                'tenant_id' => $this->tenantId,
                'payment_account_id' => $account->id,
                'created_at' => now(),
            ]);
        });
    }
}
