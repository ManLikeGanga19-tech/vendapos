<?php

namespace App\Console\Commands;

use GdImage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Turns source photos in resources/images into responsive AVIF + WebP
 * variants under public/images, so phones only download what they can show.
 */
#[Signature('marketing:images {--source= : Source directory} {--target= : Output directory}')]
#[Description('Generate responsive AVIF and WebP variants of marketing photos')]
class BuildImages extends Command
{
    /** Phone, tablet / small laptop, and large desktop. */
    public const array WIDTHS = [480, 960, 1600];

    private const array FORMATS = ['avif' => 50, 'webp' => 72];

    public function handle(): int
    {
        $source = $this->stringOption('source') ?? resource_path('images');
        $target = $this->stringOption('target') ?? public_path('images');

        if (! is_dir($source)) {
            $this->warn("No source directory at {$source}; nothing to do.");

            return self::SUCCESS;
        }

        File::ensureDirectoryExists($target);
        $formats = $this->supportedFormats();
        $manifest = [];

        foreach (File::allFiles($source) as $file) {
            if (! in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png'], true)) {
                continue;
            }

            $name = str_replace('\\', '/', substr($file->getRelativePathname(), 0, -strlen($file->getExtension()) - 1));
            $image = $this->load($file->getPathname());
            [$width, $height] = [imagesx($image), imagesy($image)];

            // Never upscale; always keep at least the original width.
            $widths = array_filter(self::WIDTHS, fn (int $w) => $w < $width);
            $widths = array_values(array_unique([...$widths, min($width, max(self::WIDTHS))]));

            File::ensureDirectoryExists(dirname("{$target}/{$name}"));

            foreach ($widths as $w) {
                $resized = imagescale($image, $w, (int) round($height * $w / $width), IMG_BICUBIC);

                if ($resized === false) {
                    throw new RuntimeException("Could not resize {$name} to {$w}px.");
                }

                foreach ($formats as $format => $quality) {
                    $this->write($resized, "{$target}/{$name}-{$w}.{$format}", $format, $quality);
                }
            }

            $manifest[$name] = [
                'width' => $width,
                'height' => $height,
                'widths' => $widths,
                'formats' => array_keys($formats),
            ];

            $this->line("  {$name} → ".implode(', ', $widths).' px');
        }

        ksort($manifest);
        File::put("{$target}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $this->info(count($manifest).' image(s) optimised.');

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? rtrim($value, '/\\') : null;
    }

    /** @return array<string, int> */
    private function supportedFormats(): array
    {
        $gd = gd_info();

        return array_filter(self::FORMATS, fn (int $q, string $format) => match ($format) {
            'avif' => ($gd['AVIF Support'] ?? false) === true,
            'webp' => ($gd['WebP Support'] ?? false) === true,
        }, ARRAY_FILTER_USE_BOTH) ?: throw new RuntimeException('GD needs WebP support to build images.');
    }

    private function load(string $path): GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            throw new RuntimeException("Could not read image {$path}.");
        }

        return $image;
    }

    private function write(GdImage $image, string $path, string $format, int $quality): void
    {
        $written = match ($format) {
            'avif' => imageavif($image, $path, $quality, 6),
            'webp' => imagewebp($image, $path, $quality),
            default => throw new RuntimeException("Unsupported format {$format}."),
        };

        if (! $written) {
            throw new RuntimeException("Could not write {$path}.");
        }
    }
}
