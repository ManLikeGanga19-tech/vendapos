<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Widgets;

use App\Domain\Analytics\Actions\SummariseSalesDay;
use App\Domain\Etims\Actions\CountOpenSubmissions;
use App\Domain\Tenancy\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Locked;
use VendaPOS\Core\ValueObjects\Money;

/** One tenant's business day so far, from the rollups, plus its eTIMS backlog. */
class TenantToday extends StatsOverviewWidget
{
    #[Locked]
    public ?Tenant $record = null;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        if ($this->record === null) {
            return [];
        }

        $today = now((string) config('vendapos.business_timezone'))->toDateString();

        [$day, $etims] = $this->record->run(fn () => [
            app(SummariseSalesDay::class)->handle($today),
            app(CountOpenSubmissions::class)->handle(),
        ]);

        return [
            Stat::make(__('admin.today.sales'), Money::fromCents($day['total_cents'])->format())
                ->description(__('admin.today.vat', ['vat' => Money::fromCents($day['vat_cents'])->format()])),
            Stat::make(__('admin.today.orders'), (string) $day['orders']),
            Stat::make(__('admin.today.voids_cancels'), "{$day['voids']} / {$day['cancels']}")
                ->description(__('admin.today.voids_cancels_help')),
            Stat::make(__('admin.today.etims'), "{$etims['pending']} / {$etims['failed']}")
                ->description(__('admin.today.etims_help'))
                ->color($etims['failed'] > 0 ? 'danger' : null)
                ->icon($etims['failed'] > 0 ? 'heroicon-o-exclamation-triangle' : null),
        ];
    }
}
