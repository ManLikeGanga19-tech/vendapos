<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Orders\Actions\FlagHighVoidRates;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Queue\LiveTenants;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:void-rates {--date= : Business day (Y-m-d, Africa/Nairobi); yesterday by default}')]
#[Description('Flag staff who voided more than the limit share of their sales on a business day')]
final class FlagVoidRatesCommand extends Command
{
    public function handle(FlagHighVoidRates $flag): int
    {
        $timezone = (string) config('vendapos.business_timezone');
        $option = $this->option('date');
        $date = is_string($option) && $option !== ''
            ? CarbonImmutable::createFromFormat('Y-m-d', $option, $timezone)
            : CarbonImmutable::now($timezone)->subDay();

        if (! $date instanceof CarbonImmutable) {
            $this->error('The date must look like 2026-11-21.');

            return self::INVALID;
        }

        $flagged = 0;

        tenancy()->runForMultiple(LiveTenants::all(), function (Tenant $tenant) use ($flag, $date, &$flagged) {
            $flagged += $flag->handle($date->startOfDay());
        });

        $this->info("Void rates for {$date->toDateString()}: {$flagged} staff member(s) over the limit.");

        return self::SUCCESS;
    }
}
