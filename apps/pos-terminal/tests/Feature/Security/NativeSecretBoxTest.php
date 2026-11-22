<?php

declare(strict_types=1);

use App\Security\KeyringUnavailable;
use App\Security\NativeSecretBox;
use Illuminate\Support\Facades\Process;
use Native\Desktop\Facades\System;

it('refuses when the OS has no keyring', function () {
    System::shouldReceive('canEncrypt')->andReturn(false);
    System::shouldReceive('encrypt')->never();

    expect(fn () => (new NativeSecretBox)->encrypt('token'))->toThrow(KeyringUnavailable::class);
});

it('refuses in production on Linux without a secret service', function () {
    $this->app->detectEnvironment(fn () => 'production');
    System::shouldReceive('canEncrypt')->andReturn(true);
    System::shouldReceive('encrypt')->never();
    Process::fake(['*busctl*' => Process::result("NAME PID PROCESS\norg.freedesktop.DBus 1 systemd\n")]);

    expect(fn () => (new NativeSecretBox)->encrypt('token'))
        ->toThrow(KeyringUnavailable::class, 'gnome-keyring');
    Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), 'busctl'));
})->skip(PHP_OS_FAMILY !== 'Linux', 'Linux-only check');

it('encrypts in production when a secret service is there', function () {
    $this->app->detectEnvironment(fn () => 'production');
    System::shouldReceive('canEncrypt')->andReturn(true);
    System::shouldReceive('encrypt')->with('token')->andReturn('c2VhbGVk');
    Process::fake(['*busctl*' => Process::result("NAME PID PROCESS\norg.freedesktop.secrets - - (activatable)\n")]);

    expect((new NativeSecretBox)->encrypt('token'))->toBe('c2VhbGVk');
})->skip(PHP_OS_FAMILY !== 'Linux', 'Linux-only check');

it('fails loudly when the keyring cannot decrypt', function () {
    System::shouldReceive('decrypt')->andReturn(null);

    expect(fn () => (new NativeSecretBox)->decrypt('garbage'))->toThrow(KeyringUnavailable::class);
});
