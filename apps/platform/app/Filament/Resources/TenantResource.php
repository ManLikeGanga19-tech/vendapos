<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Resources\TenantResource\Pages;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

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
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
