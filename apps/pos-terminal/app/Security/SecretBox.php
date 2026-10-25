<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Encrypts small secrets, like the device token, so they are never stored in
 * plain text on the till.
 */
interface SecretBox
{
    /** @throws KeyringUnavailable */
    public function encrypt(string $plainText): string;

    /** @throws KeyringUnavailable */
    public function decrypt(string $cipherText): string;
}
