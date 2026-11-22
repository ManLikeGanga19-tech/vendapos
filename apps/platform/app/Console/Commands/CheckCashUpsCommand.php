<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\CashUp\Actions\FlagStaleShifts;
use App\Domain\CashUp\Models\CashCheck;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Notifications\CashChecksNotice;
use App\Infrastructure\Queue\LiveTenants;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cash-up:check')]
#[Description('Flag shifts left open and lost shifts, then tell owners about new cash checks in one digest at most every half hour')]
final class CheckCashUpsCommand extends Command
{
    public function handle(FlagStaleShifts $flagStaleShifts): int
    {
        $digestMinutes = (int) config('vendapos.cash_up.check_digest_minutes');
        $notified = 0;

        tenancy()->runForMultiple(LiveTenants::all(), function (Tenant $tenant) use ($flagStaleShifts, $digestMinutes, &$notified) {
            $flagStaleShifts->handle();

            // One digest at most every $digestMinutes, and only for checks not told about yet.
            $lastDigest = CashCheck::query()->max('notified_at');

            if ($lastDigest !== null && now()->subMinutes($digestMinutes)->lt($lastDigest)) {
                return;
            }

            $new = CashCheck::query()->where('status', CashCheck::OPEN)->whereNull('notified_at')->get(['id', 'kind']);

            if ($new->isEmpty()) {
                return;
            }

            CashCheck::query()->whereKey($new->pluck('id'))->update(['notified_at' => now()]);
            $tenant->notify(new CashChecksNotice($new->countBy('kind')->all()));
            $notified++;
        });

        $this->info("Told {$notified} owner(s) about cash to check.");

        return self::SUCCESS;
    }
}
