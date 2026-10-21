<?php

it('renders the home page in each language with no missing translations', function (string $locale, string $heading) {
    $this->get("/{$locale}")
        ->assertOk()
        ->assertSee($heading)
        ->assertDontSee('home.')
        ->assertDontSee('nav.')
        ->assertDontSee('footer.');
})->with([
    ['en', 'Run every till, tab and branch from one place.'],
    ['sw', 'Simamia kila mashine ya mauzo, kila akaunti na kila tawi mahali pamoja.'],
]);

it('splits VAT out of the illustrative till total with integer cents', function () {
    $this->get('/en')
        ->assertSeeInOrder(['KES 3,491.38', 'KES 558.62', 'KES 4,050.00'])
        ->assertSee('Pay KES 4,050.00 with M-Pesa');
});

it('shows a photo slot with translated alt text for every vertical', function () {
    $response = $this->get('/sw')->assertOk();

    foreach (__('home.verticals.items', [], 'sw') as $key => $vertical) {
        $response->assertSee('data-image-placeholder="verticals/'.$key.'"', false)
            ->assertSee('aria-label="'.e($vertical['alt']).'"', false);
    }
});

it('marks unconfirmed prices and figures as placeholders', function () {
    $this->get('/en')
        ->assertSee('KES 3,499')
        ->assertSee('KES 0B+')
        ->assertSee('TBC');
});

it('drops the placeholder marker once figures are confirmed', function () {
    config([
        'marketing.prices_confirmed' => true,
        'marketing.contact.confirmed' => true,
        'marketing.stats' => ['uptime' => ['value' => '99.95%', 'confirmed' => true]],
    ]);

    $this->get('/en')
        ->assertSee('KES 7,999')
        ->assertSee('99.95%')
        ->assertDontSee('TBC');
});

it('prices the enterprise plan as custom', function () {
    $this->get('/sw')->assertSee('Bei maalum');
});
