<?php

it('renders both legal documents in both languages without missing translations', function (string $document, string $locale) {
    $response = $this->get("/{$locale}/{$document}")
        ->assertOk()
        ->assertSee(e(__("legal.{$document}.title", [], $locale)), false);

    expect($response->getContent())->not->toMatch('/\b(legal|common|nav|footer)\.[a-z_]+\.[a-z_]/');

    foreach (__("legal.{$document}.sections", [], $locale) as $key => $section) {
        $response->assertSee('id="'.$key.'"', false)
            ->assertSee('href="#'.$key.'"', false)
            ->assertSee(e($section['title']), false);
    }
})->with(['privacy', 'terms'])->with(['en', 'sw']);

it('shows the last-updated date in the visitor’s language', function () {
    $this->get('/en/privacy')->assertSee('Last updated 21 October 2026');
    $this->get('/sw/privacy')->assertSee('Ilisasishwa mwisho 21 Oktoba 2026');
});

it('marks the documents as drafts until legal review is complete', function () {
    $this->get('/en/terms')->assertSee('Draft: this document is being reviewed');

    config(['marketing.legal.reviewed' => true]);

    $this->get('/en/terms')->assertDontSee('Draft: this document is being reviewed');
});

it('names DanamoTech and marks unknown company facts as TBC', function () {
    $this->get('/en/privacy')
        ->assertSeeInOrder(['Company', 'DanamoTech Ltd', 'Based in', 'Mombasa, Kenya'])
        ->assertSeeInOrder(['Registration number', 'TBC'])
        ->assertSeeInOrder(['ODPC registration', 'TBC']);

    config(['marketing.company.registration_number' => 'PVT-ABC123']);

    $this->get('/en/privacy')->assertSee('PVT-ABC123');
});

it('states the demo lead retention period from config', function () {
    config(['marketing.leads.retention_months' => 18]);

    $this->get('/en/privacy')->assertSee('Demo requests: 18 months after we last contact you');
    $this->get('/sw/privacy')->assertSee('miezi 18 baada ya mawasiliano yetu');
});

it('links the legal pages from every footer and the privacy policy from the demo form', function () {
    $this->get('/sw/pricing')
        ->assertSee('href="'.url('/sw/privacy').'"', false)
        ->assertSee('href="'.url('/sw/terms').'"', false);

    $this->get('/en/demo')->assertSee('Read our full privacy policy');
});

it('serves the legal pages without cookies, like other public pages', function () {
    expect($this->get('/en/privacy')->headers->getCookies())->toBe([]);
});
