<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Security\SecretBox;

/** Reversible stand-in for the OS keyring; the cipher text never equals the plain text. */
final class FakeSecretBox implements SecretBox
{
    public function encrypt(string $plainText): string
    {
        return 'sealed:'.base64_encode(strrev($plainText));
    }

    public function decrypt(string $cipherText): string
    {
        return strrev((string) base64_decode(substr($cipherText, strlen('sealed:')), true));
    }
}
