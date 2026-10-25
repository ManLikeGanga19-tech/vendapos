<?php

it('renders the product page in both languages', function (string $locale, string $title) {
    $response = $this->get("/{$locale}/product")->assertOk()->assertSee($title);

    expect($response->getContent())->not->toMatch('/\b(product|plans|common|nav|footer)\.[a-z_]+/');
})->with([
    ['en', 'Everything your counter, kitchen and head office need.'],
    ['sw', 'Kila kitu ambacho kaunta, jiko na ofisi kuu yako zinahitaji.'],
]);

it('has a section and jump link for every feature', function () {
    $response = $this->get('/en/product');

    foreach (['till', 'payments', 'etims', 'offline', 'stock', 'branches', 'staff', 'kitchen'] as $section) {
        $response->assertSee('id="'.$section.'"', false)->assertSee('href="#'.$section.'"', false);
    }
});

it('uses the M-Pesa and KRA marks on the payment and eTIMS features', function () {
    $html = $this->get('/en/product')->getContent();

    expect(substr_count($html, asset('brand/mpesa.png')))->toBeGreaterThanOrEqual(4)
        ->and(substr_count($html, asset('brand/kra.png')))->toBeGreaterThanOrEqual(3);
});

it('computes the illustrative receipt VAT from integer cents', function () {
    // KES 360.00 VAT-inclusive at 16%: VAT = 360 × 16 / 116 = 49.66.
    $this->get('/en/product')->assertSeeInOrder(['VAT 16%', 'KES 49.66', 'Total', 'KES 360.00']);
});

it('sums the dashboard illustration across the Mombasa branches', function () {
    $this->get('/en/product')
        ->assertSee('KES 407,280.00')
        ->assertSeeInOrder(['Mombasa CBD', 'Nyali', 'Diani']);
});
