<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Tenancy\Actions\CreateBranch;
use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use VendaPOS\Core\Enums\BusinessType;

/**
 * The owner's branches (T7). A new branch runs one of the business's modules,
 * which decides its tills' screens and this panel's pages for it. Adding a
 * module is done by VendaPOS; owners only see what they have.
 */
class Branches extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 19;

    protected static ?string $slug = 'branches';

    public static function canAccess(): bool
    {
        $staff = Filament::auth()->user();

        return $staff instanceof Staff && $staff->roleName() === 'owner';
    }

    public static function getNavigationLabel(): string
    {
        return __('owner.branches.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.branches.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => app(ListBranches::class)->handle())
            ->description(__('owner.branches.description'))
            ->columns([
                TextColumn::make('name')->label(__('owner.branches.name')),
                TextColumn::make('business_type')->label(__('owner.branches.type'))
                    ->formatStateUsing(fn (string $state): string => __("owner.branches.types.{$state}")),
            ])
            ->headerActions([
                Action::make('add')
                    ->label(__('owner.branches.add'))
                    ->authorize(fn () => static::canAccess())
                    ->schema([
                        TextInput::make('name')->label(__('owner.branches.name'))->required()->maxLength(CreateBranch::NAME_MAX),
                        Select::make('business_type')
                            ->label(__('owner.branches.type'))
                            ->helperText(__('owner.branches.type_help'))
                            ->options(fn (): array => self::moduleOptions())
                            ->required()
                            ->selectablePlaceholder(false),
                    ])
                    ->action(function (array $data) {
                        app(CreateBranch::class)->handle((string) $data['name'], BusinessType::from((string) $data['business_type']));
                        $this->flushCachedTableRecords();
                        Notification::make()->title(__('owner.branches.added'))->success()->send();
                    }),
            ]);
    }

    /** @return array<string, string> the business's modules that a branch can run */
    private static function moduleOptions(): array
    {
        $tenant = tenant();
        $options = [];

        foreach ($tenant instanceof Tenant ? $tenant->moduleTypes() : [] as $type) {
            if ($type->isAvailable()) {
                $options[$type->value] = __("owner.branches.types.{$type->value}");
            }
        }

        return $options;
    }
}
