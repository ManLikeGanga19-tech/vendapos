<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Orders\Models\VoidCheck;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Notifications\VoidChecksNotice;
use App\Infrastructure\Queue\LiveTenants;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:check-voids')]
#[Description('Tell owners about new voids to check, in one digest at most every half hour')]
final class CheckVoidsCommand extends Command
{
    public function handle(): int
    {
        $digestMinutes = (int) config('vendapos.voids.check_digest_minutes');
        $notified = 0;

        tenancy()->runForMultiple(LiveTenants::all(), function (Tenant $tenant) use ($digestMinutes, &$notified) {
            // One digest at most every $digestMinutes, and only for checks not told about yet.
            $lastDigest = VoidCheck::query()->max('notified_at');

            if ($lastDigest !== null && now()->subMinutes($digestMinutes)->lt($lastDigest)) {
                return;
            }

            $new = VoidCheck::query()->where('status', VoidCheck::OPEN)->whereNull('notified_at')->get(['id', 'kind']);

            if ($new->isEmpty()) {
                return;
            }

            VoidCheck::query()->whereKey($new->pluck('id'))->update(['notified_at' => now()]);
            $tenant->notify(new VoidChecksNotice($new->countBy('kind')->all()));
            $notified++;
        });

        $this->info("Told {$notified} owner(s) about voids to check.");

        return self::SUCCESS;
    }
}
