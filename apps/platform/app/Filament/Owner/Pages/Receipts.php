<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderLine;
use App\Domain\Orders\Models\OrderTender;
use App\Domain\Orders\Models\ReceiptReprint;
use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Verticals\Models\Ticket;
use App\Filament\Owner\Concerns\HasReportFilters;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Enums\TenderMethod;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\Tax\VatBand;
use VendaPOS\Core\Tax\VatBreakdown;
use VendaPOS\Core\ValueObjects\Money;

/**
 * Every sale's receipt, for audit: searchable by the number printed on the
 * slip or the M-Pesa code, with who sold it, at which till and table, and how
 * often it was printed again. Owners see every branch, managers their own.
 * Read-only: nothing here changes a sale.
 */
class Receipts extends Page implements HasTable
{
    use HasReportFilters;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'reports/receipts';

    /** @var array<string, string>|null */
    private ?array $branchNames = null;

    /** @var array<string, string>|null device ID => "T2 · Front counter", read once per request */
    private ?array $tillNames = null;

    /** @var array<string, string>|null */
    private ?array $staffNames = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('owner.reports.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner.receipts.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.receipts.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Order::query()
                ->whereIn('branch_id', $this->visibleBranchIds())
                ->with('tenders')
                ->withCount('reprints'))
            ->filters([...$this->periodAndBranch(), ...$this->receiptFilters()], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(['md' => 2, 'xl' => 4])
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->defaultSort('placed_at', 'desc')
            ->searchPlaceholder(__('owner.receipts.search'))
            ->emptyStateHeading(__('owner.receipts.none'))
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('receipt_number')->label(__('owner.receipts.number'))
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->searchable(query: fn (Builder $query, string $search) => self::search($query, $search)),
                TextColumn::make('placed_at')->label(__('owner.receipts.time'))
                    ->dateTime('D j M Y, H:i', (string) config('vendapos.business_timezone'))
                    ->sortable(),
                TextColumn::make('branch_id')->label(__('owner.receipts.branch'))
                    ->formatStateUsing(fn (string $state): string => $this->branchNames()[$state] ?? '—'),
                TextColumn::make('device_id')->label(__('owner.receipts.till'))
                    ->formatStateUsing(fn (string $state): string => $this->tillNames()[$state] ?? '—'),
                TextColumn::make('staff_id')->label(__('owner.receipts.staff'))
                    ->formatStateUsing(fn (?string $state): string => $this->staffNames()[$state ?? ''] ?? '—')
                    ->placeholder('—'),
                TextColumn::make('total_cents')->label(__('owner.receipts.total'))->alignEnd()->weight('bold')
                    ->formatStateUsing(fn (int $state): string => Money::fromCents($state)->format()),
                TextColumn::make('paid_by')->label(__('owner.receipts.paid_by'))
                    ->state(fn (Order $record): string => $this->paidBy($record))
                    ->placeholder('—'),
                TextColumn::make('status')->label(__('owner.receipts.status'))
                    ->formatStateUsing(fn (OrderStatus $state): string => __("owner.receipts.statuses.{$state->value}")),
                TextColumn::make('reprints_count')->label(__('owner.receipts.reprints'))->alignEnd()
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? '—' : (string) $state),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('owner.receipts.view'))
                    ->modalHeading(fn (Order $record): string => __('owner.receipts.heading', ['number' => $record->receipt_number ?? "#{$record->sequence_number}"]))
                    ->modalContent(fn (Order $record): View => $this->receipt($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('owner.receipts.close')),
            ]);
    }

    /**
     * The report period and branch, applied to the sales. The branch only
     * ever narrows the person's own branches (see ScopesToStaffBranches).
     *
     * @return list<BaseFilter>
     */
    private function periodAndBranch(): array
    {
        return array_map(fn (BaseFilter $filter): BaseFilter => match ($filter->getName()) {
            'period' => $filter->query(function (Builder $query, array $data): Builder {
                [$from, $to] = $this->rangeFrom(['period' => $data]);
                $zone = (string) config('vendapos.business_timezone');

                return $query->where('placed_at', '>=', CarbonImmutable::parse($from, $zone)->utc())
                    ->where('placed_at', '<', CarbonImmutable::parse($to, $zone)->addDay()->utc());
            }),
            'branch' => $filter->query(fn (Builder $query, array $data): Builder => $query->whereIn('branch_id', $this->reportBranchIds(['branch' => $data]))),
            default => $filter,
        }, $this->reportFilters());
    }

    /** @return list<BaseFilter> */
    private function receiptFilters(): array
    {
        return [
            SelectFilter::make('device_id')->label(__('owner.receipts.till'))
                ->placeholder(__('owner.receipts.all_tills'))
                ->options(fn (): array => $this->tillNames())
                ->native(),
            SelectFilter::make('staff_id')->label(__('owner.receipts.staff'))
                ->placeholder(__('owner.receipts.all_staff'))
                ->options(fn (): array => $this->staffNames())
                ->native(),
            SelectFilter::make('paid_by')->label(__('owner.receipts.paid_by'))
                ->placeholder(__('owner.receipts.any_payment'))
                ->options(['cash' => __('owner.receipts.cash'), 'mpesa' => __('owner.receipts.mpesa')])
                ->native()
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'cash' => $query->whereHas('tenders', fn (Builder $t) => $t->where('method', TenderMethod::Cash)),
                    'mpesa' => $query->whereHas('tenders', fn (Builder $t) => $t->whereIn('method', [TenderMethod::MpesaPush, TenderMethod::MpesaCode])),
                    default => $query,
                }),
            SelectFilter::make('status')->label(__('owner.receipts.status'))
                ->placeholder(__('owner.receipts.any_status'))
                ->options(array_combine(
                    array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()),
                    array_map(fn (OrderStatus $s) => __("owner.receipts.statuses.{$s->value}"), OrderStatus::cases()),
                ))
                ->native(),
        ];
    }

    /**
     * A receipt number ("T2-000123", or just "123") or an M-Pesa code, in any case.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    private static function search(Builder $query, string $search): Builder
    {
        $term = strtoupper(trim($search));

        return $query->where(fn (Builder $q) => $q
            ->where('receipt_number', 'like', "%{$term}%")
            ->orWhereHas('tenders', fn (Builder $t) => $t->where('mpesa_reference', $term)));
    }

    private function paidBy(Order $order): string
    {
        return $order->tenders
            ->map(fn (OrderTender $tender): string => $tender->method === TenderMethod::Cash
                ? __('owner.receipts.cash')
                : trim(__('owner.receipts.mpesa').' '.$tender->mpesa_reference))
            ->unique()
            ->implode(', ');
    }

    /** The receipt as the till printed it, with who printed it again and when. */
    private function receipt(Order $order): View
    {
        // A record from the table query is already scoped; re-check, since the ID came from the browser.
        abort_unless(in_array($order->branch_id, $this->visibleBranchIds(), true), 403);

        $order->loadMissing(['lines', 'tenders', 'reprints']);
        $zone = (string) config('vendapos.business_timezone');
        $bands = VatBreakdown::fromLines($order->lines->map(fn (OrderLine $line) => $line->toOrderLineData())->all());

        return view('owner.receipt', [
            'order' => $order,
            'time' => $order->placed_at->setTimezone($zone)->format('D j M Y, H:i'),
            'branch' => $this->branchNames()[$order->branch_id] ?? '—',
            'till' => $this->tillNames()[$order->device_id] ?? '—',
            'staff' => $this->staffNames()[$order->staff_id ?? ''] ?? null,
            'table' => $this->ticketTitle($order->ticket_id),
            'net' => Money::fromCents($order->total_cents - $order->vat_cents)->format(),
            'bands' => array_map(fn (VatBand $band): array => [
                'label' => $band->category === VatCategory::Exempt
                    ? __('owner.receipts.vat_exempt', ['amount' => $band->net()->format()])
                    : __('owner.receipts.vat_rate', ['rate' => $band->category->rate() / 100, 'amount' => $band->net()->format()]),
                'vat' => $band->vat->format(),
            ], $bands),
            'paidBy' => $this->paidBy($order),
            'reprints' => $order->reprints->sortBy('reprinted_at')->map(fn (ReceiptReprint $reprint): string => __('owner.receipts.reprinted', [
                'time' => $reprint->reprinted_at->setTimezone($zone)->format('D j M Y, H:i'),
                'name' => $this->staffNames()[$reprint->staff_id] ?? '—',
                'till' => $this->tillNames()[$reprint->device_id] ?? '—',
            ]))->values()->all(),
        ]);
    }

    /** "Table 4", "Takeaway: Otieno", or null for a quick sale. */
    private function ticketTitle(?string $ticketId): ?string
    {
        if ($ticketId === null) {
            return null;
        }

        $ticket = Ticket::query()->with('table')->find($ticketId);

        return match (true) {
            $ticket === null => null,
            $ticket->table !== null => (string) __('owner.receipts.table', ['name' => $ticket->table->name]),
            filled($ticket->label) => (string) __('owner.receipts.takeaway_for', ['name' => $ticket->label]),
            default => (string) __('owner.receipts.takeaway'),
        };
    }

    /** @return array<string, string> */
    private function branchNames(): array
    {
        return $this->branchNames ??= array_column(app(ListBranches::class)->handle($this->visibleBranchIds()), 'name', 'id');
    }

    /** @return array<string, string> */
    private function tillNames(): array
    {
        // Devices are central: always filter by this business, then by the person's branches.
        return $this->tillNames ??= Device::query()
            ->ofTenantBranches((string) tenant()?->getTenantKey(), $this->visibleBranchIds())
            ->orderBy('till_number')
            ->get()
            ->mapWithKeys(fn (Device $device): array => [$device->id => "{$device->tillCode()} · {$device->name}"])
            ->all();
    }

    /** @return array<string, string> */
    private function staffNames(): array
    {
        return $this->staffNames ??= Staff::query()->orderBy('name')->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(string) $id => (string) $name])
            ->all();
    }
}
