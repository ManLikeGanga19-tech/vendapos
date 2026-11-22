<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Orders\Models\VoidCheck;
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
use Illuminate\Database\Eloquent\Builder;

/**
 * Voids that need a second look (T5a-2): approved by someone who may not
 * approve voids, voided on a later business day, or one person voiding too
 * many of their sales in a day. Owners see every branch, managers their own.
 * A person marks each one checked, with a note; nothing here undoes a void.
 */
class VoidChecks extends Page implements HasTable
{
    use InteractsWithTable;
    use ScopesToStaffBranches;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 24;

    protected static ?string $slug = 'voids-to-check';

    /** @var array<string, string>|null branch ID => name, read once per request */
    private ?array $branchNames = null;

    /** @var array<string, string>|null staff ID => name, read once per request */
    private ?array $staffNames = null;

    public static function getNavigationLabel(): string
    {
        return __('owner.void_checks.title');
    }

    public static function getNavigationBadge(): ?string
    {
        $staff = filament()->auth()->user();

        if (! $staff instanceof Staff) {
            return null;
        }

        $open = self::scoped(VoidCheck::query(), $staff->visibleBranchIds(), $staff->roleName() === 'owner')
            ->where('status', VoidCheck::OPEN)->count();

        return $open > 0 ? (string) $open : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.void_checks.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => self::scoped(VoidCheck::query(), $this->visibleBranchIds(), $this->staff()->roleName() === 'owner'))
            ->defaultSort('created_at', 'desc')
            ->description(__('owner.void_checks.description'))
            ->emptyStateHeading(__('owner.void_checks.none'))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('owner.void_checks.status'))
                    ->options([VoidCheck::OPEN => __('owner.void_checks.open'), VoidCheck::RESOLVED => __('owner.void_checks.resolved')])
                    ->default(VoidCheck::OPEN)
                    ->selectablePlaceholder(false),
            ])
            ->deferFilters(false)
            ->columns([
                TextColumn::make('created_at')->label(__('owner.void_checks.found'))->since(),
                TextColumn::make('kind')->label(__('owner.void_checks.kind'))
                    ->formatStateUsing(fn (string $state): string => __("owner.void_checks.kinds.{$state}"))
                    ->description(fn (VoidCheck $record): string => $this->detail($record))
                    ->wrap(),
                TextColumn::make('business_date')->label(__('owner.void_checks.day'))->date('d/m/Y')->placeholder('—'),
                TextColumn::make('branch_id')->label(__('owner.void_checks.branch'))
                    ->formatStateUsing(fn (?string $state): string => $this->branchNames()[$state] ?? '—'),
                TextColumn::make('staff_id')->label(__('owner.void_checks.staff'))
                    ->formatStateUsing(fn (?string $state): string => $this->staffName($state)),
                TextColumn::make('resolved_note')->label(__('owner.void_checks.note'))->placeholder('—'),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->label(__('owner.void_checks.resolve'))
                    ->visible(fn (VoidCheck $record) => $record->status === VoidCheck::OPEN)
                    ->authorize(fn (VoidCheck $record) => $this->canSee($record))
                    ->schema([
                        TextInput::make('note')->label(__('owner.void_checks.note'))
                            ->placeholder(__('owner.void_checks.note_example'))
                            ->required()->maxLength(250),
                    ])
                    ->action(function (VoidCheck $record, array $data) {
                        $record->update([
                            'status' => VoidCheck::RESOLVED,
                            'resolved_by' => $this->staff()->id,
                            'resolved_note' => (string) $data['note'],
                            'resolved_at' => now(),
                        ]);
                        $this->flushCachedTableRecords();
                        Notification::make()->title(__('owner.void_checks.resolved_notice'))->success()->send();
                    }),
            ]);
    }

    /**
     * Owners see every check; managers their branches only.
     *
     * @param  Builder<VoidCheck>  $query
     * @param  list<string>  $branchIds
     * @return Builder<VoidCheck>
     */
    private static function scoped(Builder $query, array $branchIds, bool $owner): Builder
    {
        return $owner ? $query : $query->whereIn('branch_id', $branchIds);
    }

    private function canSee(VoidCheck $check): bool
    {
        return $this->staff()->roleName() === 'owner' || in_array($check->branch_id, $this->visibleBranchIds(), true);
    }

    private function detail(VoidCheck $check): string
    {
        $details = $check->details ?? [];
        $reason = is_string($details['reason'] ?? null) ? __("owner.void_reasons.{$details['reason']}") : '—';

        return match ($check->kind) {
            VoidCheck::APPROVER_NOT_ALLOWED => $check->order_id !== null
                ? __('owner.void_checks.details.approver_not_allowed_sale', ['receipt' => (string) ($details['receipt_number'] ?? '—'), 'reason' => $reason, 'approver' => $this->staffName($check->approved_by)])
                : __('owner.void_checks.details.approver_not_allowed_line', ['quantity' => (int) ($details['quantity'] ?? 0), 'item' => (string) ($details['item'] ?? '—'), 'reason' => $reason, 'approver' => $this->staffName($check->approved_by)]),
            VoidCheck::LATE_VOID => __('owner.void_checks.details.late_void', [
                'receipt' => (string) ($details['receipt_number'] ?? '—'),
                'sold_on' => is_string($details['sold_on'] ?? null) ? date('d/m/Y', (int) strtotime($details['sold_on'])) : '—',
                'voided_on' => $check->business_date?->format('d/m/Y') ?? '—',
                'reason' => $reason,
            ]),
            VoidCheck::HIGH_VOID_RATE => __('owner.void_checks.details.high_void_rate', [
                'voided' => (int) ($details['voided'] ?? 0),
                'sales' => (int) ($details['sales'] ?? 0),
                'percent' => (int) ($details['percent'] ?? 0),
            ]),
            default => '',
        };
    }

    private function staffName(?string $id): string
    {
        if ($id === null) {
            return '—';
        }

        // Every staff member these checks name, read once.
        $this->staffNames ??= app(FindStaffNames::class)->handle(array_values(array_filter(array_merge(
            VoidCheck::query()->distinct()->pluck('staff_id')->all(),
            VoidCheck::query()->distinct()->pluck('approved_by')->all(),
        ), 'is_string')));

        return $this->staffNames[$id] ?? '—';
    }

    /** @return array<string, string> */
    private function branchNames(): array
    {
        return $this->branchNames ??= array_column(app(ListBranches::class)->handle(), 'name', 'id');
    }
}
