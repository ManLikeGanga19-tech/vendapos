<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Encrypts small secrets, like the device token, so they are never stored in
 * plain text on the till.
 */
interface SecretBox
{
    /**
     * Throws KeyringUnavailable if secrets cannot be stored safely. Checked
     * before pairing, since the server uses up the code as soon as it answers.
     */
    public function ensureAvailable(): void;

    /** Throws KeyringUnavailable. */
    public function encrypt(string $plainText): string;

    /** Throws KeyringUnavailable. */
    public function decrypt(string $cipherText): string;
}
