<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Actions\MatchMpesaCodes;
use App\Domain\Payments\Models\MpesaCheck;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Notifications\MpesaChecksNotice;
use App\Infrastructure\Queue\LiveTenants;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('payments:check-mpesa')]
#[Description('Flag typed M-Pesa codes Safaricom never confirmed and paid pushes no sale claims, then tell owners in one digest')]
final class CheckMpesaCommand extends Command
{
    public function handle(MatchMpesaCodes $match): int
    {
        $grace = (int) config('vendapos.payments.typed_code_grace_minutes');
        $digestMinutes = (int) config('vendapos.payments.check_digest_minutes');
        $notified = 0;

        tenancy()->runForMultiple(LiveTenants::all(), function (Tenant $tenant) use ($match, $grace, $digestMinutes, &$notified) {
            DB::transaction(fn () => $match->sweep($grace));

            // One digest at most every $digestMinutes, and only for checks not told about yet.
            $lastDigest = MpesaCheck::query()->max('notified_at');

            if ($lastDigest !== null && now()->subMinutes($digestMinutes)->lt($lastDigest)) {
                return;
            }

            $new = MpesaCheck::query()->where('status', MpesaCheck::OPEN)->whereNull('notified_at')->get(['id', 'kind']);

            if ($new->isEmpty()) {
                return;
            }

            MpesaCheck::query()->whereKey($new->pluck('id'))->update(['notified_at' => now()]);
            $tenant->notify(new MpesaChecksNotice($new->countBy('kind')->all()));
            $notified++;
        });

        $this->info("Told {$notified} owner(s) about M-Pesa to check.");

        return self::SUCCESS;
    }
}
