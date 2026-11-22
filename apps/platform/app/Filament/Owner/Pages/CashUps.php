<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\CashUp\Models\CashMovement;
use App\Domain\CashUp\Models\Shift;
use App\Domain\Tenancy\Actions\FindStaffNames;
use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Actions\NotifyTills;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Owner\Concerns\ScopesToStaffBranches;
use App\Infrastructure\Realtime\TillsShouldSync;
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
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use VendaPOS\Core\Enums\KesDenomination;
use VendaPOS\Core\ValueObjects\Money;

/**
 * Every till drawer's shifts (cash-up): the float, the server's expected
 * cash, the blind count and the difference, with each shift's Z detail.
 * Owners see every branch, managers their own. Only the owner sets how far
 * a count may be off before closing needs a manager.
 */
class CashUps extends Page implements HasTable
{
    use InteractsWithTable;
    use ScopesToStaffBranches;

    /** Above this the allowance is a typo, not a policy. */
    public const int MAX_ALLOWANCE_CENTS = 10_000_00;

    public const string OPEN = 'open';

    public const string CLOSED = 'closed';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 22;

    protected static ?string $slug = 'cash-ups';

    /** @var array<string, string>|null */
    private ?array $branchNames = null;

    /** @var array<string, string>|null */
    private ?array $staffNames = null;

    /** @var array<string, string>|null device ID => "T2" */
    private ?array $tillCodes = null;

    public static function getNavigationLabel(): string
    {
        return __('owner.cash_ups.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.cash_ups.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Shift::query()->whereIn('branch_id', $this->visibleBranchIds()))
            ->defaultSort('opened_at', 'desc')
            ->description(fn (): string => __('owner.cash_ups.description', ['allowed' => Money::fromCents(self::allowedCents())->format()]))
            ->emptyStateHeading(__('owner.cash_ups.none'))
            ->filters([
                SelectFilter::make('state')
                    ->label(__('owner.cash_ups.state'))
                    ->options([self::OPEN => __('owner.cash_ups.open'), self::CLOSED => __('owner.cash_ups.closed')])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        self::OPEN => $query->whereNull('closed_at'),
                        self::CLOSED => $query->whereNotNull('closed_at'),
                        default => $query,
                    }),
                SelectFilter::make('branch_id')
                    ->label(__('owner.cash_ups.branch'))
                    ->options(fn (): array => array_intersect_key($this->branchNames(), array_flip($this->visibleBranchIds()))),
            ])
            ->deferFilters(false)
            ->columns([
                TextColumn::make('number')->label(__('owner.cash_ups.z'))
                    ->formatStateUsing(fn (Shift $record): string => $this->zNumber($record)),
                TextColumn::make('branch_id')->label(__('owner.cash_ups.branch'))
                    ->formatStateUsing(fn (string $state): string => $this->branchNames()[$state] ?? '—'),
                TextColumn::make('opened_at')->label(__('owner.cash_ups.opened'))
                    ->dateTime('d/m/Y H:i', (string) config('vendapos.business_timezone'))
                    ->description(fn (Shift $record): string => $this->staffName($record->opened_by)),
                TextColumn::make('closed_at')->label(__('owner.cash_ups.closed'))
                    ->dateTime('d/m/Y H:i', (string) config('vendapos.business_timezone'))
                    ->placeholder(__('owner.cash_ups.still_open')),
                TextColumn::make('float_cents')->label(__('owner.cash_ups.float'))->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::fromCents($state)->format()),
                TextColumn::make('expected_cents')->label(__('owner.cash_ups.expected'))->alignEnd()
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Money::fromCents($state)->format())
                    ->placeholder('—'),
                TextColumn::make('counted_cents')->label(__('owner.cash_ups.counted'))->alignEnd()
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Money::fromCents($state)->format())
                    ->placeholder('—'),
                TextColumn::make('variance_cents')->label(__('owner.cash_ups.variance'))->alignEnd()
                    ->formatStateUsing(fn (?int $state): string => self::variance($state))
                    ->color(fn (?int $state): ?string => match (true) {
                        $state === null || $state === 0 => null,
                        abs($state) > self::allowedCents() => 'danger',
                        default => 'warning',
                    })
                    ->placeholder('—'),
            ])
            ->headerActions([
                Action::make('allowance')
                    ->label(__('owner.cash_ups.allowance'))
                    ->visible(fn () => $this->isOwner())
                    ->authorize(fn () => $this->isOwner())
                    ->fillForm(fn (): array => ['allowance' => Money::fromCents(self::allowedCents())->toShillings()])
                    ->schema([
                        TextInput::make('allowance')->label(__('owner.cash_ups.allowance'))
                            ->helperText(__('owner.cash_ups.allowance_help'))
                            ->prefix('KES')
                            ->required()
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                                $cents = self::shillingsToCents($value);

                                if ($cents === null || $cents > self::MAX_ALLOWANCE_CENTS) {
                                    $fail(__('owner.cash_ups.allowance_invalid'));
                                }
                            }),
                    ])
                    ->action(fn (array $data) => $this->saveAllowance((int) self::shillingsToCents($data['allowance']))),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('owner.cash_ups.view'))
                    ->modalHeading(fn (Shift $record): string => __('owner.cash_ups.heading', ['z' => $this->zNumber($record)]))
                    ->modalContent(fn (Shift $record): View => $this->detail($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('owner.cash_ups.close')),
            ]);
    }

    /** The business's allowance; tills follow it from their next sync. */
    public static function allowedCents(): int
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant->cash_variance_allowed_cents : Tenant::DEFAULT_CASH_VARIANCE_ALLOWED_CENTS;
    }

    /** "-KES 50.00" short, "+KES 20.00" over. */
    public static function variance(?int $cents): string
    {
        if ($cents === null) {
            return '—';
        }

        return ($cents > 0 ? '+' : ($cents < 0 ? '-' : '')).Money::fromCents(abs($cents))->format();
    }

    private function saveAllowance(int $cents): void
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant && $this->isOwner(), 403);

        $before = $tenant->cash_variance_allowed_cents;
        $tenant->update(['cash_variance_allowed_cents' => $cents]);

        // A money control: who changed it, from what, to what.
        Log::channel('security')->info('cash_up.allowance_changed', [
            'tenant_id' => $tenant->getTenantKey(),
            'staff_id' => $this->staff()->id,
            'from_cents' => $before,
            'to_cents' => $cents,
        ]);

        app(NotifyTills::class)->ofTenant((string) $tenant->getTenantKey(), TillsShouldSync::DEVICE);
        Notification::make()->title(__('owner.cash_ups.allowance_saved'))->success()->send();
    }

    private function detail(Shift $shift): View
    {
        abort_unless(in_array($shift->branch_id, $this->visibleBranchIds(), true), 403);

        $money = fn (?int $cents): string => $cents === null ? '—' : Money::fromCents($cents)->format();
        $count = function (?array $counts): array {
            $rows = [];

            foreach (KesDenomination::cases() as $denomination) {
                $number = (int) ($counts[$denomination->value] ?? 0);

                if ($number > 0) {
                    $rows[] = [
                        'label' => $denomination->value()->format(),
                        'count' => $number,
                        'total' => $denomination->value()->multiply($number)->format(),
                    ];
                }
            }

            return $rows;
        };
        $timezone = (string) config('vendapos.business_timezone');

        return view('owner.cash-up', [
            'shift' => $shift,
            'till' => $this->tillCodes()[$shift->device_id] ?? '—',
            'branch' => $this->branchNames()[$shift->branch_id] ?? '—',
            'opened' => $shift->opened_at->setTimezone($timezone)->format('d/m/Y H:i').' · '.$this->staffName($shift->opened_by),
            'closed' => $shift->closed_at === null ? null : $shift->closed_at->setTimezone($timezone)->format('d/m/Y H:i').' · '.$this->staffName($shift->closed_by),
            'float' => $money($shift->float_cents),
            'cashTaken' => $money($shift->cash_taken_cents),
            'payOuts' => $money($shift->pay_outs_cents),
            'drops' => $money($shift->drops_cents),
            'voidRefunds' => $money($shift->void_refunds_cents),
            'expected' => $money($shift->expected_cents),
            'tillExpected' => $shift->till_expected_cents !== null && $shift->till_expected_cents !== $shift->expected_cents ? $money($shift->till_expected_cents) : null,
            'counted' => $money($shift->counted_cents),
            'variance' => self::variance($shift->variance_cents),
            'approvedBy' => $shift->approved_by === null ? null : $this->staffName($shift->approved_by),
            'openingCounts' => $count($shift->opening_counts),
            'closingCounts' => $count($shift->closing_counts),
            'movements' => $shift->movements()->get()->map(fn (CashMovement $movement) => [
                'when' => $movement->moved_at->setTimezone($timezone)->format('H:i'),
                'kind' => __("owner.cash_ups.kinds.{$movement->kind->value}"),
                'amount' => $money($movement->amount_cents),
                'who' => $this->staffName($movement->staff_id).($movement->approved_by !== null && $movement->approved_by !== $movement->staff_id ? ' / '.$this->staffName($movement->approved_by) : ''),
                'reason' => $movement->reason,
            ])->all(),
        ]);
    }

    private function zNumber(Shift $shift): string
    {
        return ($this->tillCodes()[$shift->device_id] ?? 'T?').'-Z'.str_pad((string) $shift->number, 4, '0', STR_PAD_LEFT);
    }

    private function isOwner(): bool
    {
        return $this->staff()->roleName() === 'owner';
    }

    private static function shillingsToCents(mixed $value): ?int
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{1,9}(\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        return Money::fromShillings($value)->cents;
    }

    private function staffName(?string $id): string
    {
        if ($id === null) {
            return '—';
        }

        // Looked up as rows need them and kept for the request: a page shows few people.
        $this->staffNames ??= [];

        if (! array_key_exists($id, $this->staffNames)) {
            $this->staffNames[$id] = app(FindStaffNames::class)->handle([$id])[$id] ?? '—';
        }

        return $this->staffNames[$id];
    }

    /** @return array<string, string> */
    private function tillCodes(): array
    {
        $tenant = tenant();

        return $this->tillCodes ??= $tenant instanceof Tenant
            ? Device::query()->ofTenantBranches((string) $tenant->getTenantKey(), $this->visibleBranchIds())->get()
                ->mapWithKeys(fn (Device $device) => [$device->id => $device->tillCode()])->all()
            : [];
    }

    /** @return array<string, string> */
    private function branchNames(): array
    {
        return $this->branchNames ??= array_column(app(ListBranches::class)->handle(), 'name', 'id');
    }
}
