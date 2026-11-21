<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Catalogue\Actions\DeleteCategory;
use App\Domain\Catalogue\Actions\ListCategories;
use App\Domain\Catalogue\Actions\SaveCategory;
use App\Filament\Owner\Concerns\ManagesMenu;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/** The menu's categories, in the order tills show them. */
class MenuCategories extends Page implements HasTable
{
    use InteractsWithTable;
    use ManagesMenu;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'menu/categories';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return self::menuWord('group');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner.menu.categories');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.menu.categories');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => array_column(app(ListCategories::class)->handle(), null, 'id'))
            ->paginated(false)
            ->description(fn (): ?string => $this->canManageMenu() ? null : self::menuWord('read_only'))
            ->emptyStateHeading(__('owner.menu.no_categories'))
            ->columns([
                TextColumn::make('name')->label(__('owner.menu.name')),
                TextColumn::make('sort_order')->label(__('owner.menu.sort_order'))->alignEnd(),
                TextColumn::make('item_count')->label(__('owner.menu.item_count'))->alignEnd(),
            ])
            ->headerActions([
                Action::make('addCategory')
                    ->label(__('owner.menu.add_category'))
                    ->visible(fn () => $this->canManageMenu())
                    ->authorize(fn () => $this->canManageMenu())
                    ->schema($this->categoryForm())
                    ->action(fn (Action $action, array $data) => $this->changeMenu(
                        $action,
                        fn () => app(SaveCategory::class)->handle(null, $data['name'], (int) ($data['sort_order'] ?? 0)),
                        __('owner.menu.saved'),
                    )),
            ])
            ->recordActions([
                Action::make('editCategory')
                    ->label(__('owner.menu.edit_category'))
                    ->visible(fn () => $this->canManageMenu())
                    ->authorize(fn () => $this->canManageMenu())
                    ->fillForm(fn (array $record) => ['name' => $record['name'], 'sort_order' => $record['sort_order']])
                    ->schema($this->categoryForm())
                    ->action(fn (Action $action, array $record, array $data) => $this->changeMenu(
                        $action,
                        fn () => app(SaveCategory::class)->handle($record['id'], $data['name'], (int) ($data['sort_order'] ?? 0)),
                        __('owner.menu.saved'),
                    )),
                Action::make('deleteCategory')
                    ->label(__('owner.menu.delete_category'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(self::menuWord('delete_category_confirm'))
                    ->visible(fn () => $this->canManageMenu())
                    ->authorize(fn () => $this->canManageMenu())
                    ->action(fn (Action $action, array $record) => $this->changeMenu(
                        $action,
                        fn () => app(DeleteCategory::class)->handle($record['id']),
                        __('owner.menu.deleted_notice'),
                    )),
            ]);
    }

    /** @return list<TextInput> */
    private function categoryForm(): array
    {
        return [
            TextInput::make('name')->label(__('owner.menu.name'))->required()->maxLength(60),
            TextInput::make('sort_order')->label(__('owner.menu.sort_order'))
                ->helperText(__('owner.menu.sort_order_help'))
                ->integer()->minValue(0)->maxValue(9999)->default(0),
        ];
    }
}
