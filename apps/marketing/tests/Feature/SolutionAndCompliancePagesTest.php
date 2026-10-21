<?php

it('renders the new pages in both languages without missing translations', function (string $path, string $text) {
    $response = $this->get($path)->assertOk()->assertSee($text);

    expect($response->getContent())->not->toMatch('/\b(restaurants|compliance|product|common|nav|footer)\.[a-z_]+\.[a-z_]/');
})->with([
    ['/en/solutions/restaurants', 'Turn tables faster, without order mix-ups.'],
    ['/sw/solutions/restaurants', 'Hudumia meza haraka zaidi, bila kuchanganya oda.'],
    ['/en/compliance', 'Built to keep KRA, your customers and your auditors satisfied.'],
    ['/sw/compliance', 'Imeundwa kuwaridhisha KRA, wateja wako na wakaguzi wako.'],
]);

it('counts occupied tables on the floor plan illustration', function () {
    $this->get('/en/solutions/restaurants')->assertSee('8 of 12 tables occupied');
});

it('reuses the kitchen display illustration', function () {
    $this->get('/en/solutions/restaurants')->assertSee('Grill station')->assertSee('Bump');
});

it('links Solutions in the header and the restaurant card on the home page', function () {
    $this->get('/sw')
        ->assertSee('href="'.url('/sw/solutions').'"', false)
        ->assertSee('href="'.url('/sw/solutions/restaurants').'"', false)
        ->assertSee('Jifunze zaidi');

    $this->get('/en/solutions/restaurants')->assertSee('aria-current="page"', false);
});

it('shows eTIMS certification as in progress until KRA approves', function () {
    $this->get('/en/compliance')->assertSee('In progress')->assertSee('TBC')->assertDontSee('Certified');

    config(['marketing.etims_certified' => true, 'marketing.contact.confirmed' => true]);

    $this->get('/en/compliance')->assertSee('Certified')->assertDontSee('In progress');
});

it('explains how M-Pesa confirmations are verified', function () {
    $this->get('/en/compliance')
        ->assertSee('secret callback address')
        ->assertSee('Safaricom’s published IP addresses', false)
        ->assertSee(asset('brand/kra.png'), false)
        ->assertSee(asset('brand/mpesa.png'), false);
});

it('names Danamo Tech Limited as accountable for platform data', function () {
    $this->get('/sw/compliance')->assertSee('Danamo Tech Limited ya Mombasa, Kenya');
});
