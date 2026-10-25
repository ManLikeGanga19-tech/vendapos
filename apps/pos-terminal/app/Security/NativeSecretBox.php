<?php

declare(strict_types=1);

namespace App\Security;

use Illuminate\Support\Facades\Process;
use Native\Desktop\Facades\System;

/**
 * Electron's safeStorage, backed by the OS keyring.
 *
 * On Linux with no secret service, Electron falls back to its "basic_text"
 * backend, which encrypts with a hard-coded password: no protection at all.
 * NativePHP cannot tell us which backend Electron picked, so production
 * builds also require a secret service on the session D-Bus (gnome-keyring on
 * the pilot image) and refuse rather than store the token weakly. The till is
 * launched with --password-store=gnome-libsecret so Electron uses it.
 */
final class NativeSecretBox implements SecretBox
{
    private const string SECRET_SERVICE = 'org.freedesktop.secrets';

    public function encrypt(string $plainText): string
    {
        $this->ensureKeyring();

        // The facade's docblock says string; System::encrypt() really returns ?string.
        /** @var string|null $cipherText */
        $cipherText = System::encrypt($plainText);

        return $cipherText ?? throw new KeyringUnavailable('The keyring could not encrypt the token.');
    }

    public function decrypt(string $cipherText): string
    {
        /** @var string|null $plainText */
        $plainText = System::decrypt($cipherText);

        return $plainText ?? throw new KeyringUnavailable('The keyring could not decrypt the token.');
    }

    private function ensureKeyring(): void
    {
        if (! System::canEncrypt()) {
            throw new KeyringUnavailable('No keyring is available to protect the token.');
        }

        if (app()->isProduction() && PHP_OS_FAMILY === 'Linux' && ! $this->secretServiceRunning()) {
            throw new KeyringUnavailable('No secret service (gnome-keyring) is running on this till.');
        }
    }

    private function secretServiceRunning(): bool
    {
        // `list` includes names that D-Bus can start on demand, as gnome-keyring usually is.
        $result = Process::timeout(5)->run(['busctl', '--user', 'list']);

        return $result->successful() && str_contains($result->output(), self::SECRET_SERVICE);
    }
}
