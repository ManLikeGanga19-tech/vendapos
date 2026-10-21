<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Jobs\QueryPaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use VendaPOS\Core\Enums\PaymentStatus;

#[Signature('payments:reconcile')]
#[Description('Queue a status query for every pending payment whose callback is overdue, in every tenant')]
final class ReconcilePaymentsCommand extends Command
{
    public function handle(): int
    {
        $queued = 0;

        tenancy()->runForMultiple(null, function (Tenant $tenant) use (&$queued) {
            Payment::query()
                ->where('status', PaymentStatus::Pending)
                ->where('created_at', '>=', now()->subHours((int) config('vendapos.payments.reconcile.stop_after_hours')))
                ->where(fn (Builder $query) => $query
                    ->where('initiated_at', '<=', now()->subSeconds((int) config('vendapos.payments.reconcile.first_query_after_seconds')))
                    ->orWhere(fn (Builder $query) => $query
                        ->whereNull('provider_request_id')
                        ->where('created_at', '<=', now()->subMinutes((int) config('vendapos.payments.reconcile.never_pushed_after_minutes')))))
                ->pluck('id')
                ->each(function (string $paymentId) use ($tenant, &$queued) {
                    QueryPaymentStatus::dispatch((string) $tenant->getTenantKey(), $paymentId);
                    $queued++;
                });
        });

        $this->info("Queued {$queued} payment status queries.");

        return self::SUCCESS;
    }
}
