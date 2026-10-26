<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Payments\Models\MpesaCheck;
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
use VendaPOS\Core\ValueObjects\Money;

/**
 * M-Pesa money that does not add up yet: typed codes Safaricom never
 * confirmed, amounts that differ, codes used twice, paid pushes with no sale.
 * Owners see every branch, managers their own. A person marks each one
 * checked, with a note; nothing here changes a sale or a payment.
 */
class MpesaChecks extends Page implements HasTable
{
    use InteractsWithTable;
    use ScopesToStaffBranches;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?int $navigationSort = 23;

    protected static ?string $slug = 'mpesa-to-check';

    /** @var array<string, string>|null branch ID => name, read once per request */
    private ?array $branchNames = null;

    public static function getNavigationLabel(): string
    {
        return __('owner.mpesa_checks.title');
    }

    public static function getNavigationBadge(): ?string
    {
        $staff = filament()->auth()->user();

        if (! $staff instanceof Staff) {
            return null;
        }

        $open = self::scoped(MpesaCheck::query(), $staff->visibleBranchIds(), $staff->roleName() === 'owner')
            ->where('status', MpesaCheck::OPEN)->count();

        return $open > 0 ? (string) $open : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.mpesa_checks.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => self::scoped(MpesaCheck::query(), $this->visibleBranchIds(), $this->staff()->roleName() === 'owner'))
            ->defaultSort('created_at', 'desc')
            ->description(__('owner.mpesa_checks.description'))
            ->emptyStateHeading(__('owner.mpesa_checks.none'))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('owner.mpesa_checks.status'))
                    ->options([MpesaCheck::OPEN => __('owner.mpesa_checks.open'), MpesaCheck::RESOLVED => __('owner.mpesa_checks.resolved')])
                    ->default(MpesaCheck::OPEN)
                    ->selectablePlaceholder(false),
            ])
            ->deferFilters(false)
            ->columns([
                TextColumn::make('created_at')->label(__('owner.mpesa_checks.found'))->since(),
                TextColumn::make('kind')->label(__('owner.mpesa_checks.kind'))
                    ->formatStateUsing(fn (string $state): string => __("owner.mpesa_checks.kinds.{$state}"))
                    ->description(fn (MpesaCheck $record): string => $this->detail($record)),
                TextColumn::make('branch_id')->label(__('owner.mpesa_checks.branch'))
                    ->formatStateUsing(fn (?string $state): string => $this->branchNames()[$state] ?? '—'),
                TextColumn::make('mpesa_code')->label(__('owner.mpesa_checks.code'))->fontFamily('mono')->placeholder('—'),
                TextColumn::make('amount_cents')->label(__('owner.mpesa_checks.amount'))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Money::fromCents($state)->format()),
                TextColumn::make('resolved_note')->label(__('owner.mpesa_checks.note'))
                    ->formatStateUsing(fn (?string $state): string => $state === 'settled_automatically' ? __('owner.mpesa_checks.settled_automatically') : (string) $state)
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->label(__('owner.mpesa_checks.resolve'))
                    ->visible(fn (MpesaCheck $record) => $record->status === MpesaCheck::OPEN)
                    ->authorize(fn (MpesaCheck $record) => $this->canSee($record))
                    ->schema([
                        TextInput::make('note')->label(__('owner.mpesa_checks.note'))
                            ->placeholder(__('owner.mpesa_checks.note_example'))
                            ->required()->maxLength(250),
                    ])
                    ->action(function (MpesaCheck $record, array $data) {
                        $record->update([
                            'status' => MpesaCheck::RESOLVED,
                            'resolved_by' => $this->staff()->id,
                            'resolved_note' => (string) $data['note'],
                            'resolved_at' => now(),
                        ]);
                        $this->flushCachedTableRecords();
                        Notification::make()->title(__('owner.mpesa_checks.resolved_notice'))->success()->send();
                    }),
            ]);
    }

    /**
     * Owners see every check, including ones no branch could be found for; managers their branches only.
     *
     * @param  Builder<MpesaCheck>  $query
     * @param  list<string>  $branchIds
     * @return Builder<MpesaCheck>
     */
    private static function scoped(Builder $query, array $branchIds, bool $owner): Builder
    {
        return $owner ? $query : $query->whereIn('branch_id', $branchIds);
    }

    private function canSee(MpesaCheck $check): bool
    {
        return $this->staff()->roleName() === 'owner' || in_array($check->branch_id, $this->visibleBranchIds(), true);
    }

    private function detail(MpesaCheck $check): string
    {
        return match ($check->kind) {
            MpesaCheck::AMOUNT_MISMATCH => __('owner.mpesa_checks.details.amount_mismatch', [
                'confirmed' => Money::fromCents((int) ($check->details['confirmed_cents'] ?? 0))->format(),
            ]),
            MpesaCheck::CODE_REUSED => __('owner.mpesa_checks.details.code_reused', ['count' => count((array) ($check->details['order_ids'] ?? []))]),
            default => __("owner.mpesa_checks.details.{$check->kind}"),
        };
    }

    /** @return array<string, string> */
    private function branchNames(): array
    {
        return $this->branchNames ??= array_column(app(ListBranches::class)->handle(), 'name', 'id');
    }
}
