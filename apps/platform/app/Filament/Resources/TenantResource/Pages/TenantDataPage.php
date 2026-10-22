<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Resources\TenantResource;
use App\Models\User;
use Closure;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

/**
 * A staff page over one tenant's own data. Rows are read inside the tenant
 * with Tenant::run() and handed to the table as plain arrays, so an Admin
 * request never leaves tenancy initialised on a reused Octane worker.
 * Filament runs no policies for array rows, so access is checked here and
 * again in every action.
 */
abstract class TenantDataPage extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = TenantResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(static::isStaff(), 403);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public static function isStaff(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole('platform-admin');
    }

    protected function tenant(): Tenant
    {
        $tenant = $this->getRecord();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function inTenant(Closure $callback): mixed
    {
        return $this->tenant()->run($callback);
    }

    protected function staffEmail(): string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->email : 'unknown';
    }
}
