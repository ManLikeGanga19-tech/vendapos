<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Catalogue\Actions\ListCategories;
use App\Domain\Catalogue\Actions\ListMenuItems;
use App\Domain\Catalogue\Actions\SaveMenuItem;
use App\Domain\Catalogue\Actions\SetMenuItemOnSale;
use App\Filament\Owner\Concerns\ManagesMenu;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use UnitEnum;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\ValueObjects\Money;

/**
 * The menu's items: one price for every branch, archived rather than
 * deleted. Paginated and searched in the database.
 */
class MenuItems extends Page implements HasTable
{
    use InteractsWithTable;
    use ManagesMenu;

    /** Printable characters without spaces, as scanners and stock systems produce them. */
    private const CODE_PATTERN = '/^[\x21-\x7E]+$/';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'menu/items';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return self::menuWord('group');
    }

    public static function getNavigationLabel(): string
    {
        return self::menuWord('items');
    }

    public function getTitle(): string|Htmlable
    {
        return self::menuWord('items');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters, ?string $search, int|string $page, int|string $recordsPerPage): LengthAwarePaginator => $this->rows($filters, $search, (int) $page, (int) $recordsPerPage))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->searchable()
            ->searchPlaceholder(__('owner.menu.search'))
            ->description(fn (): ?string => $this->canManageMenu() ? null : self::menuWord('read_only'))
            ->emptyStateHeading(fn (): string => filled($this->getTableSearch())
                ? __('owner.menu.no_match', ['search' => $this->getTableSearch()])
                : __('owner.menu.no_items'))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('owner.menu.status'))
                    ->options([
                        ListMenuItems::ON_SALE => __('owner.menu.on_sale'),
                        ListMenuItems::ARCHIVED => __('owner.menu.archived'),
                    ])
                    ->default(ListMenuItems::ON_SALE)
                    ->selectablePlaceholder(false),
                SelectFilter::make('category')
                    ->label(__('owner.menu.category'))
                    ->options(fn (): array => [
                        ListMenuItems::NO_CATEGORY => __('owner.menu.no_category'),
                        ...array_column(app(ListCategories::class)->handle(), 'name', 'id'),
                    ]),
            ])
            ->deferFilters(false)
            ->columns([
                TextColumn::make('name')->label(__('owner.menu.name')),
                TextColumn::make('category')->label(__('owner.menu.category'))->placeholder(__('owner.menu.no_category')),
                TextColumn::make('type')->label(__('owner.menu.type'))
                    ->formatStateUsing(fn (string $state) => __('owner.menu.types.'.$state)),
                TextColumn::make('price_cents')->label(__('owner.menu.price'))->alignEnd()
                    ->formatStateUsing(fn (int $state) => Money::fromCents($state)->format()),
                TextColumn::make('vat_category')->label(__('owner.menu.vat_category'))
                    ->formatStateUsing(fn (string $state) => __('owner.menu.vat_categories.'.$state)),
                TextColumn::make('sku')->label(__('owner.menu.sku'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('barcode')->label(__('owner.menu.barcode'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('addItem')
                    ->label(__('owner.menu.add_item'))
                    ->visible(fn () => $this->canManageMenu())
                    ->authorize(fn () => $this->canManageMenu())
                    ->schema($this->itemForm(creating: true))
                    ->action(fn (Action $action, array $data) => $this->changeMenu(
                        $action,
                        fn () => $this->save(null, ItemType::from($data['type']), $data),
                        __('owner.menu.saved'),
                    )),
            ])
            ->recordActions([
                Action::make('editItem')
                    ->label(__('owner.menu.edit_item'))
                    ->visible(fn (array $record) => $this->canManageMenu() && $record['on_sale'])
                    ->authorize(fn () => $this->canManageMenu())
                    ->fillForm(fn (array $record) => [
                        'name' => $record['name'],
                        'category_id' => $record['category_id'],
                        'type' => $record['type'],
                        'price' => Money::fromCents($record['price_cents'])->toShillings(),
                        'vat_category' => $record['vat_category'],
                        'sku' => $record['sku'],
                        'barcode' => $record['barcode'],
                    ])
                    ->schema($this->itemForm(creating: false))
                    ->action(fn (Action $action, array $record, array $data) => $this->changeMenu(
                        $action,
                        fn () => $this->save($record['id'], ItemType::from($record['type']), $data),
                        __('owner.menu.saved'),
                    )),
                Action::make('archiveItem')
                    ->label(__('owner.menu.archive'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('owner.menu.archive_confirm'))
                    ->visible(fn (array $record) => $this->canManageMenu() && $record['on_sale'])
                    ->authorize(fn () => $this->canManageMenu())
                    ->action(fn (Action $action, array $record) => $this->changeMenu(
                        $action,
                        fn () => app(SetMenuItemOnSale::class)->handle($record['id'], false),
                        __('owner.menu.archived_notice'),
                    )),
                Action::make('restoreItem')
                    ->label(__('owner.menu.restore'))
                    ->visible(fn (array $record) => $this->canManageMenu() && ! $record['on_sale'])
                    ->authorize(fn () => $this->canManageMenu())
                    ->action(fn (Action $action, array $record) => $this->changeMenu(
                        $action,
                        fn () => app(SetMenuItemOnSale::class)->handle($record['id'], true),
                        __('owner.menu.restored_notice'),
                    )),
            ]);
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return LengthAwarePaginator<string, array<string, mixed>>
     */
    private function rows(?array $filters, ?string $search, int $page, int $perPage): LengthAwarePaginator
    {
        $status = ($filters['status']['value'] ?? null) === ListMenuItems::ARCHIVED ? ListMenuItems::ARCHIVED : ListMenuItems::ON_SALE;
        $category = $filters['category']['value'] ?? null;
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        $result = app(ListMenuItems::class)->handle($search, is_string($category) && $category !== '' ? $category : null, $status, $page, $perPage);

        /** @var array<string, array<string, mixed>> $rows */
        $rows = array_column($result['rows'], null, 'id');

        return new LengthAwarePaginator($rows, $result['total'], $perPage, $page);
    }

    /** @param array<string, mixed> $data */
    private function save(?string $id, ItemType $type, array $data): void
    {
        app(SaveMenuItem::class)->handle(
            id: $id,
            name: (string) $data['name'],
            type: $type,
            price: Money::fromShillings((string) $data['price']),
            vatCategory: VatCategory::from((string) $data['vat_category']),
            categoryId: filled($data['category_id'] ?? null) ? (string) $data['category_id'] : null,
            sku: $data['sku'] ?? null,
            barcode: $data['barcode'] ?? null,
        );
    }

    /** @return list<Grid> */
    private function itemForm(bool $creating): array
    {
        $types = [];
        foreach (SaveMenuItem::TYPES as $type) {
            $types[$type->value] = __('owner.menu.types.'.$type->value);
        }

        $vatCategories = [];
        foreach (VatCategory::cases() as $category) {
            $vatCategories[$category->value] = __('owner.menu.vat_categories.'.$category->value);
        }

        return [
            Grid::make(2)->schema([
                TextInput::make('name')->label(__('owner.menu.name'))->required()->maxLength(120)->columnSpanFull(),
                ToggleButtons::make('type')->label(__('owner.menu.type'))
                    ->options($types)
                    ->default(ItemType::Product->value)
                    ->helperText(__('owner.menu.type_help'))
                    ->grouped()
                    ->required()
                    ->disabled(! $creating)
                    ->dehydrated($creating),
                Select::make('category_id')->label(__('owner.menu.category'))
                    ->options(fn (): array => array_column(app(ListCategories::class)->handle(), 'name', 'id'))
                    ->placeholder(__('owner.menu.no_category')),
                TextInput::make('price')->label(__('owner.menu.price'))
                    ->prefix('KES')
                    ->inputMode('decimal')
                    ->helperText(__('owner.menu.price_help'))
                    ->required()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (! self::isValidPrice($value)) {
                            $fail(__('owner.menu.price_invalid'));
                        }
                    }),
                Select::make('vat_category')->label(__('owner.menu.vat_category'))
                    ->options($vatCategories)
                    ->default(VatCategory::Standard->value)
                    ->selectablePlaceholder(false)
                    ->required(),
                TextInput::make('sku')->label(__('owner.menu.sku'))
                    ->maxLength(64)
                    ->regex(self::CODE_PATTERN)
                    ->validationMessages(['regex' => __('owner.menu.code_format')]),
                TextInput::make('barcode')->label(__('owner.menu.barcode'))
                    ->maxLength(64)
                    ->regex(self::CODE_PATTERN)
                    ->validationMessages(['regex' => __('owner.menu.code_format')]),
            ]),
        ];
    }

    private static function isValidPrice(mixed $value): bool
    {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        try {
            $price = Money::fromShillings((string) $value);
        } catch (InvalidArgumentException) {
            return false;
        }

        return $price->isPositive() && $price->cents <= SaveMenuItem::MAX_PRICE_CENTS;
    }
}
