<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

it('renders the shared layout with SEO alternates', function () {
    $this->get('/sw')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/sw').'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/').'">', false)
        ->assertSee('<meta property="og:locale" content="sw_KE">', false);
});

it('offers a flag dropdown linking to the same page in each language', function () {
    $this->get('/en')
        ->assertSee('aria-label="Language: English"', false)
        ->assertSee(asset('flags/ke.svg'), false)
        ->assertDontSee('flags/tz.svg', false)
        ->assertSeeInOrder(['href="'.url('/sw').'"', 'Kiswahili'], false)
        ->assertDontSee('type="radio"', false);
});

it('marks the current language in the selector', function () {
    $this->get('/sw')->assertSee('aria-label="Lugha: Kiswahili"', false);
});

it('serves public pages without a session so they can be cached', function () {
    $response = $this->get('/en')->assertOk();

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

it('asks visitors on outdated browsers to update, in their language', function (string $locale, string $message) {
    $this->get("/{$locale}")
        ->assertSee('<p class="browser-notice" role="alert">', false)
        ->assertSee('@supports (color: color-mix(in srgb, red, blue))', false)
        ->assertSee($message);
})->with([
    ['en', 'Your browser is out of date'],
    ['sw', 'Kivinjari chako kimepitwa na wakati'],
]);

it('ships no external JavaScript, only a tiny inline enhancement', function () {
    $html = $this->get('/en')->getContent();

    preg_match_all('#<script\b[^>]*>(.*?)</script>#s', $html, $scripts);

    expect($html)->not->toContain('<script src')
        ->and($scripts[1])->toHaveCount(1)
        ->and(strlen($scripts[1][0]))->toBeLessThan(1024);
});

it('credits DanamoTech Ltd of Mombasa in the footer', function (string $locale, string $credit) {
    $this->get("/{$locale}")
        ->assertSee('DanamoTech Ltd.')
        ->assertSee($credit)
        ->assertSee('Mombasa, Kenya');
})->with([
    ['en', 'VendaPOS is built by DanamoTech Ltd, Mombasa, Kenya.'],
    ['sw', 'VendaPOS imetengenezwa na DanamoTech Ltd, Mombasa, Kenya.'],
]);

it('marks unconfirmed contact details as placeholders', function () {
    $this->get('/sw')->assertSee('Haijathibitishwa');
});

it('keeps the design flat: no gradients in styles or views', function () {
    $files = [resource_path('css/app.css'), ...File::allFiles(resource_path('views'))];

    foreach ($files as $file) {
        expect((string) file_get_contents((string) $file))->not->toMatch('/gradient|bg-linear|bg-radial|bg-conic/i');
    }
});

it('uses supplied partner logos and falls back to icons until then', function () {
    $logo = public_path('brand/zz-test.svg');
    File::put($logo, '<svg xmlns="http://www.w3.org/2000/svg"/>');

    try {
        expect(Blade::render('<x-brand-mark name="zz-test" icon="smartphone" />'))->toContain(asset('brand/zz-test.svg'))
            ->and(Blade::render('<x-brand-mark name="zz-missing" icon="smartphone" />'))->toContain('<svg')->not->toContain('<img');
    } finally {
        File::delete($logo);
    }
});
