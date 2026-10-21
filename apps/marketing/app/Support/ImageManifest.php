<?php

namespace App\Support;

/**
 * Reads the manifest written by `marketing:images`, which records each
 * optimised photo's intrinsic size and the widths generated for it.
 *
 * @phpstan-type Entry array{width: int, height: int, widths: list<int>, formats: list<string>}
 */
final class ImageManifest
{
    /** @var array<string, Entry>|null */
    private ?array $entries = null;

    public function __construct(private readonly string $path) {}

    /** @return Entry|null */
    public function get(string $name): ?array
    {
        return $this->entries()[$name] ?? null;
    }

    /** @return array<string, Entry> */
    private function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $json = is_file($this->path) ? file_get_contents($this->path) : false;

        /** @var array<string, Entry> $decoded */
        $decoded = $json === false ? [] : (json_decode($json, true) ?: []);

        return $this->entries = $decoded;
    }
}
