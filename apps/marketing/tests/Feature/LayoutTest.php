<?php

it('renders the shared layout with SEO alternates', function () {
    $this->get('/sw')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/sw').'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/').'">', false)
        ->assertSee('<meta property="og:locale" content="sw_KE">', false);
});

it('links the language switcher to the same page in the other language', function () {
    $this->get('/en')
        ->assertSee('href="'.url('/sw').'"', false)
        ->assertSee('<span class="sr-only">Kiswahili</span>', false);
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

it('ships no JavaScript bundle', function () {
    $this->get('/en')->assertDontSee('<script', false);
});

it('marks unconfirmed contact details as placeholders', function () {
    $this->get('/sw')->assertSee('Haijathibitishwa');
});
