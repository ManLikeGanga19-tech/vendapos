<?php

use App\Support\ImageManifest;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = storage_path('framework/testing/images-'.uniqid());
    File::ensureDirectoryExists("{$this->dir}/src/verticals");
});

afterEach(fn () => File::deleteDirectory($this->dir));

function fakePhoto(string $path, int $width, int $height): void
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 244, 98, 42));
    imagejpeg($image, $path);
}

it('generates responsive variants and a manifest without upscaling', function () {
    fakePhoto("{$this->dir}/src/verticals/restaurant.jpg", 2000, 1500);
    fakePhoto("{$this->dir}/src/small.jpg", 600, 400);

    $this->artisan('marketing:images', ['--source' => "{$this->dir}/src", '--target' => "{$this->dir}/out"])
        ->assertSuccessful();

    $manifest = json_decode((string) file_get_contents("{$this->dir}/out/manifest.json"), true);

    expect($manifest['verticals/restaurant'])->toMatchArray(['width' => 2000, 'height' => 1500, 'widths' => [480, 960, 1600]])
        ->and($manifest['small']['widths'])->toBe([480, 600])
        ->and("{$this->dir}/out/verticals/restaurant-960.webp")->toBeFile()
        ->and(getimagesize("{$this->dir}/out/verticals/restaurant-960.webp")[1] ?? null)->toBe(720);
});

it('renders a responsive picture once the photo exists', function () {
    File::put("{$this->dir}/manifest.json", json_encode([
        'verticals/salon' => ['width' => 1600, 'height' => 1200, 'widths' => [480, 1600], 'formats' => ['avif', 'webp']],
    ]));
    app()->instance(ImageManifest::class, new ImageManifest("{$this->dir}/manifest.json"));

    $html = Blade::render('<x-picture name="verticals/salon" alt="Stylist at work" sizes="50vw" />');

    expect($html)
        ->toContain('type="image/avif"')
        ->toContain(asset('images/verticals/salon-480.webp').' 480w')
        ->toContain('width="1600"')
        ->toContain('loading="lazy"')
        ->toContain('alt="Stylist at work"');
});

it('falls back to an accessible placeholder until the photo is supplied', function () {
    app()->instance(ImageManifest::class, new ImageManifest("{$this->dir}/missing.json"));

    expect(Blade::render('<x-picture name="verticals/bar" alt="Busy bar counter" />'))
        ->toContain('role="img"')
        ->toContain('aria-label="Busy bar counter"')
        ->toContain('data-image-placeholder="verticals/bar"');
});
