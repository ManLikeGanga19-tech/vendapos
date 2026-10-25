<?php

declare(strict_types=1);

namespace App\Filament\Owner\Auth;

use App\Domain\Tenancy\Actions\CompleteStaffSetup;
use App\Domain\Tenancy\Models\Staff;
use Closure;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use SensitiveParameter;

/**
 * Opened from the one-time link VendaPOS (or later, the owner) sends. The
 * person picks a username and password, is signed in, and Filament then
 * makes them set up an authenticator app before anything else.
 *
 * @property-read Schema $form
 */
class SetUpAccount extends SimplePage
{
    use WithRateLimiting;

    #[Locked]
    public string $token = '';

    /** Whose link this is, so keeping their own username on a reset is allowed. */
    #[Locked]
    public ?string $staffId = null;

    public bool $linkIsOpen = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(#[SensitiveParameter] string $token): void
    {
        if (Filament::auth()->check()) {
            $this->redirect((string) Filament::getUrl());

            return;
        }

        $this->token = $token;
        $link = app(CompleteStaffSetup::class)->findOpenLink($token);
        $this->linkIsOpen = $link !== null;
        $this->staffId = $link?->staff_id;

        $this->form->fill(['name' => $link?->staff->name, 'username' => $link?->staff->username]);
    }

    public function save(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('filament-panels::auth/pages/login.notifications.throttled.title', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => $exception->minutesUntilAvailable,
                ]))
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        try {
            $staff = app(CompleteStaffSetup::class)->handle($this->token, $data['username'], $data['password']);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['data.username' => __('owner.setup.username_taken')]);
        }

        if ($staff === null) {
            $this->linkIsOpen = false;

            return;
        }

        Filament::auth()->login($staff);
        session()->regenerate();

        $this->redirect((string) Filament::getUrl());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('name')
                    ->label(__('owner.setup.name'))
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('username')
                    ->label(__('owner.auth.username'))
                    ->helperText(__('owner.setup.username_help'))
                    ->required()
                    ->autocomplete('username')
                    ->dehydrateStateUsing(fn (?string $state): string => mb_strtolower(trim((string) $state)))
                    // Checked on the lower-cased value that will be stored.
                    ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                        $username = mb_strtolower(trim((string) $value));

                        if (preg_match(CompleteStaffSetup::USERNAME_PATTERN, $username) !== 1) {
                            $fail(__('owner.setup.username_format'));
                        } elseif (Staff::where('username', $username)->whereKeyNot($this->staffId)->exists()) {
                            $fail(__('owner.setup.username_taken'));
                        }
                    })
                    ->autofocus(),
                TextInput::make('password')
                    ->label(__('filament-panels::auth/pages/password-reset/reset-password.form.password.label'))
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->required()
                    ->rule(Password::min(10))
                    ->same('passwordConfirmation'),
                TextInput::make('passwordConfirmation')
                    ->label(__('filament-panels::auth/pages/password-reset/reset-password.form.password_confirmation.label'))
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->required()
                    ->dehydrated(false),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        if (! $this->linkIsOpen) {
            return $schema->components([
                Text::make(__('owner.setup.link_closed')),
            ]);
        }

        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('owner.setup.submit'))
                            ->submit('save'),
                    ])->fullWidth()->key('form-actions'),
                ]),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.setup.title');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('owner.setup.title');
    }
}
