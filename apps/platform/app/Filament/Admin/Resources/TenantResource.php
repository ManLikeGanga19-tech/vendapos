<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Admin\Resources\TenantResource\Pages;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
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
            ]);
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
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\EditTenant::class,
            Pages\TenantBranches::class,
            Pages\TenantDevices::class,
            Pages\TenantEtims::class,
            Pages\TenantNegativeStock::class,
        ]);
    }
}
