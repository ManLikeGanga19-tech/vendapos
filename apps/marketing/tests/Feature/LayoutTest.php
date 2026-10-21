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

it('ships no JavaScript bundle', function () {
    $this->get('/en')->assertDontSee('<script', false);
});

it('marks unconfirmed contact details as placeholders', function () {
    $this->get('/sw')->assertSee('Haijathibitishwa');
});
