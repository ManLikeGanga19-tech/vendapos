<?php

namespace App\View\Components;

use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * Inline SVG icon from resources/svg/icons (vendored Lucide, ISC).
 * Inlining avoids an icon font download, which matters on low-end phones.
 */
class Icon extends Component
{
    /** @var array<string, string> */
    private static array $cache = [];

    public function __construct(public string $name) {}

    public function render(): string
    {
        return <<<'BLADE'
            <svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $paths() !!}</svg>
            BLADE;
    }

    public function paths(): string
    {
        return self::$cache[$this->name] ??= $this->load();
    }

    private function load(): string
    {
        $path = resource_path("svg/icons/{$this->name}.svg");

        if (! preg_match('/^[a-z0-9-]+$/', $this->name) || ! is_file($path)) {
            throw new InvalidArgumentException("Unknown icon [{$this->name}].");
        }

        return (string) file_get_contents($path);
    }
}
