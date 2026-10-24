<?php

declare(strict_types=1);

namespace App\Infrastructure\Exports;

use RuntimeException;

/**
 * Authenticated, chunked file encryption (libsodium secretstream,
 * XChaCha20-Poly1305), so a dump of any size is encrypted and decrypted
 * without being held in memory. Any change to the ciphertext, a truncated
 * file or the wrong key fails decryption.
 *
 * The key is VENDAPOS_EXPORT_KEY (base64 of 32 bytes), separate from
 * APP_KEY so rotating APP_KEY never makes old exports unreadable.
 */
final class StreamCipher
{
    private const int CHUNK_BYTES = 1024 * 1024;

    private const int ABYTES = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

    /** Encrypts $from into $to. */
    public function encrypt(string $from, string $to): void
    {
        [$in, $out] = [$this->open($from, 'rb'), $this->open($to, 'wb')];

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key());
            fwrite($out, $header);

            do {
                $chunk = (string) fread($in, self::CHUNK_BYTES);
                $last = feof($in);
                $tag = $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag));
            } while (! $last);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /**
     * Decrypts $from chunk by chunk, handing each plaintext chunk to $sink.
     *
     * @param  callable(string): void  $sink
     *
     * @throws RuntimeException when the file is damaged, truncated or under another key
     */
    public function decrypt(string $from, callable $sink): void
    {
        $in = $this->open($from, 'rb');

        try {
            $header = (string) fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key());
            $finished = false;

            while (! feof($in)) {
                $cipher = (string) fread($in, self::CHUNK_BYTES + self::ABYTES);

                if ($cipher === '') {
                    break;
                }

                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);

                if ($result === false) {
                    throw new RuntimeException('Export is damaged or was encrypted with another key.');
                }

                [$plain, $tag] = $result;
                $sink($plain);

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $finished = true;
                    break;
                }
            }

            if (! $finished) {
                throw new RuntimeException('Export is truncated.');
            }
        } finally {
            fclose($in);
        }
    }

    private function key(): string
    {
        $key = base64_decode((string) config('vendapos.exports.key'), true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('VENDAPOS_EXPORT_KEY must be base64 of 32 random bytes.');
        }

        return $key;
    }

    /** @return resource */
    private function open(string $path, string $mode)
    {
        $handle = fopen($path, $mode);

        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}.");
        }

        return $handle;
    }
}
