<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Payments\Actions\AssignPaymentAccountBranches;
use App\Domain\Payments\Actions\SavePaymentAccount;
use App\Domain\Payments\Exceptions\PaymentAccountRefused;
use App\Domain\Payments\Gateways\DarajaGateway;
use App\Domain\Payments\Jobs\RegisterConfirmationUrls;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Models\Staff;
use App\Filament\Owner\Concerns\ScopesToStaffBranches;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;
use VendaPOS\Core\Enums\MobileMoneyProvider;

/**
 * The business's paybills and tills: their keys, which one is the default,
 * and which branches take payments into which. Owners only. Keys are
 * write-only (the page shows their last four characters), tried with the
 * provider before saving, and every save needs a fresh authenticator code.
 */
class PaymentAccounts extends Page implements HasTable
{
    use InteractsWithTable;
    use ScopesToStaffBranches;

    /** Keys typed blind and never shown again. */
    private const array SECRETS = ['consumer_key', 'consumer_secret', 'passkey'];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 22;

    protected static ?string $slug = 'payment-accounts';

    /** @var array<string, string>|null branch ID => name, read once per request */
    private ?array $branchNames = null;

    public static function canAccess(): bool
    {
        $staff = Filament::auth()->user();

        return $staff instanceof Staff && $staff->roleName() === 'owner';
    }

    public static function getNavigationLabel(): string
    {
        return __('owner.payment_accounts.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.payment_accounts.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => PaymentAccount::query())
            ->defaultSort('name')
            ->paginated(false)
            ->description(__('owner.payment_accounts.description'))
            ->emptyStateHeading(__('owner.payment_accounts.none'))
            ->emptyStateDescription(__('owner.payment_accounts.none_help'))
            ->columns([
                TextColumn::make('name')->label(__('owner.payment_accounts.name'))
                    ->description(fn (PaymentAccount $record): ?string => $record->is_default ? (string) __('owner.payment_accounts.default') : null),
                TextColumn::make('kind')->label(__('owner.payment_accounts.kind'))
                    ->state(fn (PaymentAccount $record): string => __('owner.payment_accounts.kinds.'.($record->credentials['transaction_type'] ?? ''))
                        .' '.($record->credentials['party_b'] ?? '')),
                TextColumn::make('environment')->label(__('owner.payment_accounts.environment'))
                    ->formatStateUsing(fn (string $state): string => __('owner.payment_accounts.environments.'.$state)),
                TextColumn::make('consumer_key')->label(__('owner.payment_accounts.consumer_key'))
                    ->state(fn (PaymentAccount $record): string => $record->hint('consumer_key') ?? '—'),
                TextColumn::make('branches')->label(__('owner.payment_accounts.branches'))
                    ->state(fn (PaymentAccount $record): string => $this->branchesLabel($record)),
                // Whether typed M-Pesa codes can be confirmed for this account (C2B, T3d).
                TextColumn::make('c2b_status')->label(__('owner.payment_accounts.c2b'))
                    ->state(fn (PaymentAccount $record): string => __('owner.payment_accounts.c2b_statuses.'.($record->c2b_status ?? 'none')))
                    ->description(fn (PaymentAccount $record): ?string => $record->c2b_status === PaymentAccount::C2B_FAILED ? $record->c2b_message : null),
            ])
            ->headerActions([
                Action::make('addAccount')
                    ->label(__('owner.payment_accounts.add'))
                    ->authorize(fn () => static::canAccess())
                    ->schema(fn () => $this->accountForm(null))
                    ->action(function (Action $action, array $data) {
                        $this->change($action, fn () => $this->save(null, $data));
                        Notification::make()->title(__('owner.payment_accounts.saved'))->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('editAccount')
                    ->label(__('owner.payment_accounts.edit'))
                    ->authorize(fn () => static::canAccess())
                    ->fillForm(fn (PaymentAccount $record) => [
                        'name' => $record->name,
                        'environment' => $record->environment,
                        'transaction_type' => $record->credentials['transaction_type'] ?? null,
                        'shortcode' => $record->credentials['shortcode'] ?? null,
                        'party_b' => $record->credentials['party_b'] ?? null,
                        'is_default' => $record->is_default,
                    ])
                    ->schema(fn (PaymentAccount $record) => $this->accountForm($record))
                    ->action(function (Action $action, PaymentAccount $record, array $data) {
                        $this->change($action, fn () => $this->save($record->id, $data));
                        Notification::make()->title(__('owner.payment_accounts.saved'))->success()->send();
                    }),
                Action::make('registerC2b')
                    ->label(__('owner.payment_accounts.c2b_retry'))
                    ->authorize(fn () => static::canAccess())
                    ->visible(fn (PaymentAccount $record) => $record->c2b_status !== PaymentAccount::C2B_REGISTERED)
                    ->action(function (PaymentAccount $record) {
                        RegisterConfirmationUrls::dispatch((string) tenant('id'), $record->id);
                        $record->update(['c2b_status' => PaymentAccount::C2B_PENDING, 'c2b_message' => null]);
                        $this->flushCachedTableRecords();
                        Notification::make()->title(__('owner.payment_accounts.c2b_sent'))->success()->send();
                    }),
                Action::make('assignBranches')
                    ->label(__('owner.payment_accounts.assign'))
                    ->authorize(fn () => static::canAccess())
                    ->visible(fn () => $this->branchNames() !== [])
                    ->fillForm(fn (PaymentAccount $record) => ['branch_ids' => $this->assignedBranchIds($record)])
                    ->schema([
                        CheckboxList::make('branch_ids')->label(__('owner.payment_accounts.branches'))
                            ->options(fn () => $this->branchNames())
                            ->helperText(__('owner.payment_accounts.assign_help')),
                        $this->codeField(),
                    ])
                    ->action(function (Action $action, PaymentAccount $record, array $data) {
                        $ids = array_values(array_intersect(array_map(strval(...), (array) ($data['branch_ids'] ?? [])), array_keys($this->branchNames())));
                        $this->change($action, fn () => app(AssignPaymentAccountBranches::class)->handle($record->id, $ids, $this->staff()->id));
                        Notification::make()->title(__('owner.payment_accounts.saved'))->success()->send();
                    }),
            ]);
    }

    /** @param array<string, mixed> $data */
    private function save(?int $accountId, #[SensitiveParameter] array $data): void
    {
        $account = app(SavePaymentAccount::class)->handle(
            $accountId,
            (string) $data['name'],
            MobileMoneyProvider::MpesaKe,
            (string) ($data['environment'] ?? PaymentAccount::businessEnvironment()),
            array_intersect_key($data, array_flip(DarajaGateway::CREDENTIAL_FIELDS)),
            (bool) ($data['is_default'] ?? false) || ($accountId !== null && PaymentAccount::query()->whereKey($accountId)->where('is_default', true)->exists()),
            $this->staff()->id,
        );

        Log::channel('security')->info('owner.payment_account_saved', ['tenant_id' => tenant('id'), 'account_id' => $account->id, 'staff_id' => $this->staff()->id]);
    }

    /** Runs a change; a refusal keeps the form open with the reason. */
    private function change(Action $action, Closure $change): void
    {
        try {
            $change();
        } catch (PaymentAccountRefused $e) {
            Notification::make()->title(__('owner.payment_accounts.refused.'.$e->reason))->danger()->send();
            $action->halt();
        }

        $this->flushCachedTableRecords();
    }

    /** @return list<TextInput|Select|Toggle> */
    private function accountForm(?PaymentAccount $account): array
    {
        $environment = PaymentAccount::query()->when($account !== null, fn ($query) => $query->whereKeyNot($account?->id))->value('environment');

        $fields = [
            TextInput::make('name')->label(__('owner.payment_accounts.name'))
                ->placeholder(__('owner.payment_accounts.name_example'))
                ->required()->maxLength(60),
            Select::make('environment')->label(__('owner.payment_accounts.environment'))
                ->options(array_combine(PaymentAccount::ENVIRONMENTS, array_map(fn (string $env) => __('owner.payment_accounts.environments.'.$env), PaymentAccount::ENVIRONMENTS)))
                ->default($environment ?? PaymentAccount::SANDBOX)
                // One environment per business: fixed once another account exists.
                ->disabled($environment !== null)
                ->dehydrated($environment === null)
                ->helperText($environment !== null ? (string) __('owner.payment_accounts.environment_fixed') : null)
                ->selectablePlaceholder(false)
                ->required(),
            Select::make('transaction_type')->label(__('owner.payment_accounts.kind'))
                ->options(array_combine(
                    DarajaGateway::CREDENTIAL_OPTIONS['transaction_type'],
                    array_map(fn (string $type) => __('owner.payment_accounts.kinds.'.$type), DarajaGateway::CREDENTIAL_OPTIONS['transaction_type']),
                ))
                ->required(),
            TextInput::make('shortcode')->label(__('owner.payment_accounts.shortcode'))
                ->helperText(__('owner.payment_accounts.shortcode_help'))
                ->required()->regex('/^\d{5,7}$/'),
            TextInput::make('party_b')->label(__('owner.payment_accounts.party_b'))
                ->helperText(__('owner.payment_accounts.party_b_help'))
                ->required()->regex('/^\d{5,7}$/'),
        ];

        foreach (self::SECRETS as $secret) {
            $hint = $account?->hint($secret);

            $fields[] = TextInput::make($secret)->label(__('owner.payment_accounts.'.$secret))
                ->password()
                ->autocomplete('off')
                ->required($account === null)
                ->maxLength(200)
                ->helperText($hint !== null ? (string) __('owner.payment_accounts.keep_saved', ['hint' => $hint]) : null);
        }

        $fields[] = Toggle::make('is_default')->label(__('owner.payment_accounts.make_default'))
            ->helperText(__('owner.payment_accounts.default_help'))
            ->default(PaymentAccount::defaultFor(MobileMoneyProvider::MpesaKe) === null)
            // The default stays one until another account takes over (save() keeps it).
            ->disabled($account !== null && $account->is_default);
        $fields[] = $this->codeField();

        return $fields;
    }

    /** A fresh authenticator code: a stolen session alone cannot redirect the money. */
    private function codeField(): TextInput
    {
        return TextInput::make('code')->label(__('owner.payment_accounts.code'))
            ->helperText(__('owner.payment_accounts.code_help'))
            ->required()
            ->autocomplete('one-time-code')
            ->dehydrated(false)
            ->rule(fn (): Closure => function (string $attribute, #[SensitiveParameter] mixed $value, Closure $fail): void {
                $provider = Filament::getPanel('owner')->getMultiFactorAuthenticationProviders()['app'] ?? null;
                $staff = $this->staff();

                if ($provider instanceof AppAuthentication && is_string($value)
                    && $provider->verifyCode($value, $provider->getSecret($staff), shouldPreventCodeReuse: true)) {
                    return;
                }

                $fail(__('owner.payment_accounts.code_invalid'));
            });
    }

    /** @return list<string> */
    private function assignedBranchIds(PaymentAccount $account): array
    {
        return array_values(DB::table('payment_account_branches')
            ->where('payment_account_id', $account->id)
            ->pluck('branch_id')->map(strval(...))->all());
    }

    private function branchesLabel(PaymentAccount $account): string
    {
        $names = array_values(array_intersect_key($this->branchNames(), array_flip($this->assignedBranchIds($account))));

        if ($account->is_default) {
            $names[] = __('owner.payment_accounts.other_branches');
        }

        return $names === [] ? '—' : implode(', ', $names);
    }

    /** @return array<string, string> */
    private function branchNames(): array
    {
        return $this->branchNames ??= array_column(app(ListBranches::class)->handle(), 'name', 'id');
    }
}
