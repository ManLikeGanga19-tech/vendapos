<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Events\PaymentAccountChanged;
use App\Domain\Payments\Exceptions\PaymentAccountRefused;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Payments\Models\PaymentAccountChange;
use Illuminate\Support\Facades\DB;

/**
 * Sets which branches take payments into an account. A branch moved here
 * leaves its previous account; a branch taken off falls back to the default.
 * Payments already made keep the account they were made with. The caller
 * checks the branch IDs belong to this business and who may do this.
 */
final class AssignPaymentAccountBranches
{
    /**
     * @param  list<string>  $branchIds
     *
     * @throws PaymentAccountRefused
     */
    public function handle(int $accountId, array $branchIds, ?string $changedBy): void
    {
        DB::transaction(function () use ($accountId, $branchIds, $changedBy) {
            $account = PaymentAccount::query()->lockForUpdate()->find($accountId)
                ?? throw new PaymentAccountRefused('not_found');

            $provider = $account->provider->value;
            $before = DB::table('payment_account_branches')
                ->where('provider', $provider)->where('payment_account_id', $account->id)
                ->pluck('branch_id')->map(strval(...))->all();

            $added = array_values(array_diff(array_unique($branchIds), $before));
            $removed = array_values(array_diff($before, $branchIds));

            if ($added === [] && $removed === []) {
                return;
            }

            DB::table('payment_account_branches')
                ->where('provider', $provider)->where('payment_account_id', $account->id)
                ->whereIn('branch_id', $removed)->delete();

            $now = now();

            DB::table('payment_account_branches')->upsert(
                array_map(fn (string $branchId) => [
                    'branch_id' => $branchId,
                    'provider' => $provider,
                    'payment_account_id' => $account->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $added),
                ['branch_id', 'provider'],
                ['payment_account_id', 'updated_at'],
            );

            PaymentAccountChange::create([
                'payment_account_id' => $account->id,
                'changed_by' => $changedBy,
                'action' => PaymentAccountChange::ACTION_BRANCHES_CHANGED,
                'details' => array_filter(['added' => $added, 'removed' => $removed]),
            ]);

            PaymentAccountChanged::dispatch($account->id, $account->name, PaymentAccountChange::ACTION_BRANCHES_CHANGED, $changedBy);
        });
    }
}
