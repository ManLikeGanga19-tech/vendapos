<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Tenancy\Actions\CreateBranch;
use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Admin\Resources\TenantResource\Pages;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Rules\PhoneNumber;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id')
                    ->label('Tenant ID')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('name')
                    ->label(__('admin.tenant_form.name'))
                    ->required()
                    ->maxLength(255),
                Section::make(__('admin.tenant_form.owner'))
                    ->description(__('admin.tenant_form.owner_help'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('owner_name')
                            ->label(__('admin.tenant_form.owner_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('owner_phone')
                            ->label(__('admin.tenant_form.owner_phone'))
                            ->tel()
                            ->required()
                            ->rule(new PhoneNumber(__('admin.tenant_form.owner_phone_invalid')))
                            // Stored as E.164 so SMS providers can use it as-is.
                            ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : PhoneNumber::normalize($state)),
                        TextInput::make('owner_email')
                            ->label(__('admin.tenant_form.owner_email'))
                            ->email()
                            ->maxLength(255),
                        Select::make('locale')
                            ->label(__('admin.tenant_form.locale'))
                            ->helperText(__('admin.tenant_form.locale_help'))
                            ->options(array_combine(Tenant::LOCALES, array_map(fn (string $locale) => __("admin.tenant_form.locales.{$locale}"), Tenant::LOCALES)))
                            ->default('en')
                            ->required()
                            ->selectablePlaceholder(false),
                    ]),
                Section::make(__('admin.modules.title'))
                    ->description(__('admin.modules.help'))
                    ->schema([
                        CheckboxList::make('modules')
                            ->hiddenLabel()
                            ->options(self::moduleOptions())
                            ->descriptions(self::comingSoon())
                            ->disableOptionWhen(fn (string $value): bool => ! BusinessType::from($value)->isAvailable())
                            ->columns(3)
                            ->required()
                            ->live()
                            // A greyed-out box can still be sent: refuse modules that are not built.
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                                foreach ((array) $value as $module) {
                                    if (! is_string($module) || BusinessType::tryFrom($module)?->isAvailable() !== true) {
                                        $fail(__('admin.modules.not_available'));

                                        return;
                                    }
                                }
                            })
                            ->rule(fn (?Tenant $record) => self::keepsModulesInUse($record)),
                    ]),
                Section::make(__('admin.modules.first_branch'))
                    ->description(__('admin.modules.first_branch_help'))
                    ->columns(2)
                    ->visibleOn('create')
                    ->schema([
                        TextInput::make('first_branch_name')
                            ->label(__('admin.modules.branch_name'))
                            ->required()
                            ->maxLength(CreateBranch::NAME_MAX)
                            ->dehydrated(false),
                        Select::make('first_branch_type')
                            ->label(__('admin.modules.branch_type'))
                            ->options(fn (Get $get): array => array_intersect_key(self::moduleOptions(), array_flip(array_filter((array) $get('modules'), 'is_string'))))
                            ->required()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    /** @return array<string, string> every business type, as the admin sees it */
    public static function moduleOptions(): array
    {
        $options = [];

        foreach (BusinessType::cases() as $type) {
            $options[$type->value] = __("admin.modules.types.{$type->value}");
        }

        return $options;
    }

    /** @return array<string, string> "Coming soon" under the modules not built yet */
    private static function comingSoon(): array
    {
        $notes = [];

        foreach (BusinessType::cases() as $type) {
            if (! $type->isAvailable()) {
                $notes[$type->value] = __('admin.modules.coming_soon');
            }
        }

        return $notes;
    }

    /** A module a branch still runs cannot be taken away. */
    private static function keepsModulesInUse(?Tenant $record): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
            if ($record === null || ! $record->isActive()) {
                return;
            }

            $kept = array_filter((array) $value, 'is_string');
            $inUse = $record->run(fn () => array_values(array_unique(array_column(app(ListBranches::class)->handle(), 'business_type'))));
            $removed = array_diff($inUse, $kept);

            if ($removed !== []) {
                $fail(__('admin.modules.in_use', ['modules' => implode(', ', array_map(fn (string $type) => self::moduleOptions()[$type] ?? $type, $removed))]));
            }
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Tenant ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('admin.tenant_form.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.suspension.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("admin.suspension.statuses.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        Tenant::ACTIVE => 'success',
                        Tenant::SUSPENDED => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('owner_name')
                    ->label(__('admin.tenant_form.owner'))
                    ->description(fn (Tenant $record): ?string => $record->owner_phone)
                    ->searchable(['owner_name', 'owner_phone', 'owner_email']),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
            'branches' => Pages\TenantBranches::route('/{record}/branches'),
            'devices' => Pages\TenantDevices::route('/{record}/devices'),
            'etims' => Pages\TenantEtims::route('/{record}/etims'),
            'negative-stock' => Pages\TenantNegativeStock::route('/{record}/negative-stock'),
            'void-rates' => Pages\TenantVoidRates::route('/{record}/void-rates'),
            'exports' => Pages\TenantExports::route('/{record}/exports'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        $tenant = method_exists($page, 'getRecord') ? $page->getRecord() : null;

        // After a purge only the central pages are left.
        if ($tenant instanceof Tenant && $tenant->status === Tenant::PURGED) {
            return $page->generateNavigationItems([Pages\EditTenant::class, Pages\TenantExports::class]);
        }

        return $page->generateNavigationItems([
            Pages\EditTenant::class,
            Pages\TenantBranches::class,
            Pages\TenantDevices::class,
            Pages\TenantEtims::class,
            Pages\TenantNegativeStock::class,
            Pages\TenantVoidRates::class,
            Pages\TenantExports::class,
        ]);
    }
}
