<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Filament\Resources\TenantResource\Widgets\TenantSalesByHour;
use App\Filament\Resources\TenantResource\Widgets\TenantToday;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            TenantToday::class,
            TenantSalesByHour::class,
        ];
    }

    /**
     * No delete here: deleting a tenant drops its whole schema. Tenants are
     * suspended first and purged later by a separate, notified process.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
