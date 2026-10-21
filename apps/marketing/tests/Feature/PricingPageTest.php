<?php

it('renders the pricing page in both languages', function (string $locale, string $title) {
    $response = $this->get("/{$locale}/pricing")->assertOk()->assertSee($title);

    expect($response->getContent())->not->toMatch('/\b(pricing|plans|common|nav|footer)\.[a-z_]+/');
})->with([
    ['en', 'Simple plans that grow with your business.'],
    ['sw', 'Mipango rahisi inayokua pamoja na biashara yako.'],
]);

it('shows monthly and yearly prices from the single plan config', function () {
    // Business: KES 7,999 a month, or 10 months a year = KES 79,990 ≈ KES 6,666 a month.
    $this->get('/en/pricing')
        ->assertSee('KES 7,999')
        ->assertSee('KES 6,666')
        ->assertSee('Billed yearly at KES 79,990 per branch')
        ->assertSee('id="billing-annual"', false);
});

it('marks unconfirmed prices and plan contents as placeholders', function () {
    $this->get('/en/pricing')
        ->assertSee('Plan contents are being finalised.')
        ->assertSee('TBC');

    config(['marketing.prices_confirmed' => true, 'marketing.plan_contents_confirmed' => true, 'marketing.contact.confirmed' => true]);

    $this->get('/en/pricing')->assertDontSee('TBC')->assertDontSee('Plan contents are being finalised.');
});

it('renders every comparison row with accessible included / not included states', function () {
    $response = $this->get('/sw/pricing');

    foreach (config('marketing.plan_matrix') as $rows) {
        foreach (array_keys($rows) as $row) {
            $response->assertSee(__("pricing.matrix.rows.{$row}", [], 'sw'));
        }
    }

    $response->assertSee('<span class="sr-only">Kimejumuishwa</span>', false)
        ->assertSee('<span class="sr-only">Hakijajumuishwa</span>', false);
});

it('answers the FAQ with native disclosure widgets', function () {
    $this->get('/en/pricing')
        ->assertSee('Do you take a percentage of my sales?')
        ->assertSee('<details class="group/faq">', false);
});

it('appears in the main navigation and from the home page', function () {
    $this->get('/en')
        ->assertSee('href="'.url('/en/pricing').'"', false)
        ->assertSee('Compare every plan in detail');
});
