<?php

declare(strict_types=1);

use App\Filament\TwoFactorRequirement;

it('requires the authenticator app unless told otherwise', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);
    // The default, whatever the developer's own .env says.
    config(['vendapos.require_mfa' => true]);

    expect(TwoFactorRequirement::applies())->toBeTrue()
        ->and(TwoFactorRequirement::middleware('mfa'))->toBe(['mfa']);
})->with(['local', 'production', 'staging', 'testing']);

it('lets local development switch the authenticator app off', function () {
    app()->detectEnvironment(fn () => 'local');
    config(['vendapos.require_mfa' => false]);

    expect(TwoFactorRequirement::applies())->toBeFalse()
        ->and(TwoFactorRequirement::middleware('mfa'))->toBe([]);
});

it('ignores that switch everywhere except local development', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);
    config(['vendapos.require_mfa' => false]);

    expect(TwoFactorRequirement::applies())->toBeTrue()
        ->and(TwoFactorRequirement::middleware('mfa'))->toBe(['mfa']);
})->with(['production', 'staging', 'testing']);
