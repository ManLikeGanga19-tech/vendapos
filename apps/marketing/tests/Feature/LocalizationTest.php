<?php

use App\Support\LocalizedUrls;
use Illuminate\Http\Request;

it('redirects the bare domain to English by default', function () {
    $this->get('/')
        ->assertRedirect('/en')
        ->assertHeader('Vary', 'Accept-Language');
});

it('redirects the bare domain to Swahili when the browser prefers it', function () {
    $this->get('/', ['Accept-Language' => 'sw-KE,sw;q=0.9,en;q=0.5'])
        ->assertRedirect('/sw');
});

it('falls back to English for unsupported browser languages', function () {
    $this->get('/', ['Accept-Language' => 'fr-FR,fr;q=0.9'])
        ->assertRedirect('/en');
});

it('renders each locale server-side', function (string $locale, string $text) {
    $this->get("/{$locale}")
        ->assertOk()
        ->assertSee('<html lang="'.$locale.'"', false)
        ->assertSee($text);
})->with([
    ['en', 'Skip to main content'],
    ['sw', 'Ruka hadi maudhui makuu'],
]);

it('returns 404 for unsupported locale prefixes', function () {
    $this->get('/fr')->assertNotFound();
});

it('builds the current page URL in every locale', function () {
    $this->get('/sw');

    expect(LocalizedUrls::forRequest(app(Request::class)))->toBe([
        'en' => url('/en'),
        'sw' => url('/sw'),
    ]);
});
