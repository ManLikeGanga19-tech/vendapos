<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\CashUp\Models\CashCheck;
use App\Domain\Tenancy\Actions\FindStaffNames;
use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Models\Staff;
use App\Filament\Owner\Concerns\ScopesToStaffBranches;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use VendaPOS\Core\ValueObjects\Money;

/**
 * Cash-up problems that need a second look: a drawer off by more than
 * allowed, the till and server disagreeing, an approver who may not close
 * shifts, a shift left open, or a sale outside any shift. Owners see every
 * branch, managers their own. A person marks each checked with a note; the
 * two a late sale can put right are settled by the server when it does.
 */
class CashChecks extends Page implements HasTable
{
    use InteractsWithTable;
    use ScopesToStaffBranches;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 23;

    protected static ?string $slug = 'cash-to-check';

    /** @var array<string, string>|null */
    private ?array $branchNames = null;

    /** @var array<string, string> */
    private array $staffNames = [];

    public static function getNavigationLabel(): string
    {
        return __('owner.cash_checks.title');
    }

    public static function getNavigationBadge(): ?string
    {
        $staff = filament()->auth()->user();

        if (! $staff instanceof Staff) {
            return null;
        }

        $open = CashCheck::query()->whereIn('branch_id', $staff->visibleBranchIds())->where('status', CashCheck::OPEN)->count();

        return $open > 0 ? (string) $open : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.cash_checks.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => CashCheck::query()->whereIn('branch_id', $this->visibleBranchIds()))
            ->defaultSort('created_at', 'desc')
            ->description(__('owner.cash_checks.description'))
            ->emptyStateHeading(__('owner.cash_checks.none'))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('owner.cash_checks.status'))
                    ->options([CashCheck::OPEN => __('owner.cash_checks.open'), CashCheck::RESOLVED => __('owner.cash_checks.resolved')])
                    ->default(CashCheck::OPEN)
                    ->selectablePlaceholder(false),
            ])
            ->deferFilters(false)
            ->columns([
                TextColumn::make('created_at')->label(__('owner.cash_checks.found'))->since(),
                TextColumn::make('kind')->label(__('owner.cash_checks.kind'))
                    ->formatStateUsing(fn (string $state): string => __("owner.cash_checks.kinds.{$state}"))
                    ->description(fn (CashCheck $record): string => $this->detail($record))
                    ->wrap(),
                TextColumn::make('business_date')->label(__('owner.cash_checks.day'))->date('d/m/Y')->placeholder('—'),
                TextColumn::make('branch_id')->label(__('owner.cash_checks.branch'))
                    ->formatStateUsing(fn (?string $state): string => $this->branchNames()[$state] ?? '—'),
                TextColumn::make('staff_id')->label(__('owner.cash_checks.staff'))
                    ->formatStateUsing(fn (?string $state): string => $this->staffName($state)),
                TextColumn::make('resolved_note')->label(__('owner.cash_checks.note'))
                    ->state(fn (CashCheck $record): ?string => $record->status === CashCheck::RESOLVED && $record->resolved_by === null
                        ? (string) __('owner.cash_checks.settled')
                        : $record->resolved_note)
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->label(__('owner.cash_checks.resolve'))
                    ->visible(fn (CashCheck $record) => $record->status === CashCheck::OPEN)
                    ->authorize(fn (CashCheck $record) => in_array($record->branch_id, $this->visibleBranchIds(), true))
                    ->schema([
                        TextInput::make('note')->label(__('owner.cash_checks.note'))
                            ->placeholder(__('owner.cash_checks.note_example'))
                            ->required()->maxLength(250),
                    ])
                    ->action(function (CashCheck $record, array $data) {
                        $record->update([
                            'status' => CashCheck::RESOLVED,
                            'resolved_by' => $this->staff()->id,
                            'resolved_note' => (string) $data['note'],
                            'resolved_at' => now(),
                        ]);
                        $this->flushCachedTableRecords();
                        Notification::make()->title(__('owner.cash_checks.resolved_notice'))->success()->send();
                    }),
            ]);
    }

    private function detail(CashCheck $check): string
    {
        $details = $check->details ?? [];
        $z = 'Z'.str_pad((string) ($details['number'] ?? '?'), 4, '0', STR_PAD_LEFT);
        $amount = Money::fromCents(abs((int) $check->amount_cents))->format();

        return match ($check->kind) {
            CashCheck::VARIANCE_OVER_LIMIT => __((int) $check->amount_cents < 0 ? 'owner.cash_checks.details.short' : 'owner.cash_checks.details.over', [
                'z' => $z,
                'amount' => $amount,
                'approver' => $check->approved_by === null ? __('owner.cash_checks.nobody') : $this->staffName($check->approved_by),
            ]),
            CashCheck::FIGURES_DIFFER => __('owner.cash_checks.details.figures_differ', ['z' => $z, 'amount' => $amount]),
            CashCheck::APPROVER_NOT_ALLOWED => is_string($details['kind'] ?? null)
                ? __('owner.cash_checks.details.approver_not_allowed_movement', ['z' => $z, 'what' => __("owner.cash_ups.kinds.{$details['kind']}"), 'amount' => $amount, 'approver' => $this->staffName($check->approved_by)])
                : __('owner.cash_checks.details.approver_not_allowed_close', ['z' => $z, 'approver' => $this->staffName($check->approved_by)]),
            CashCheck::OPEN_TOO_LONG => __('owner.cash_checks.details.open_too_long', ['z' => $z, 'hours' => (int) ($details['hours'] ?? 0)]),
            CashCheck::SALE_OUTSIDE_SHIFT => __(($details['missing_shift'] ?? false) === true ? 'owner.cash_checks.details.missing_shift' : 'owner.cash_checks.details.sale_outside_shift', [
                'receipt' => (string) ($details['receipt_number'] ?? '—'),
                'amount' => $amount,
            ]),
            default => '',
        };
    }

    private function staffName(?string $id): string
    {
        if ($id === null) {
            return '—';
        }

        if (! array_key_exists($id, $this->staffNames)) {
            $this->staffNames[$id] = app(FindStaffNames::class)->handle([$id])[$id] ?? '—';
        }

        return $this->staffNames[$id];
    }

    /** @return array<string, string> */
    private function branchNames(): array
    {
        return $this->branchNames ??= array_column(app(ListBranches::class)->handle(), 'name', 'id');
    }
}
