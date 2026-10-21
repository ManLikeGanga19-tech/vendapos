<?php

$verticals = ['bars', 'salons', 'shops', 'supermarkets', 'hardware', 'pharmacy', 'liquor'];

it('renders every solution page in both languages without missing translations', function (string $vertical, string $locale) {
    $response = $this->get("/{$locale}/solutions/{$vertical}")
        ->assertOk()
        ->assertSee(e(__("solutions.{$vertical}.hero.title", [], $locale)), false)
        ->assertSee(e(__("solutions.{$vertical}.panel.title", [], $locale)), false);

    expect($response->getContent())->not->toMatch('/\b(solutions|home|common|nav|footer)\.[a-z_]+\.[a-z_]/');
})->with($verticals)->with(['en', 'sw']);

it('has copy in both languages for every configured row, problem, feature and hardware item', function (string $vertical) {
    $config = config("marketing.solutions.{$vertical}");

    foreach (['en', 'sw'] as $locale) {
        $copy = __("solutions.{$vertical}", [], $locale);

        expect(array_keys($copy['panel']['rows']))->toBe(array_keys($config['panel']['rows']))
            ->and(array_keys($copy['problems']['items']))->toBe(array_keys($config['problems']))
            ->and(array_keys($copy['features']['items']))->toBe(array_keys($config['features']))
            ->and(array_keys($copy['hardware']))->toBe(array_keys($config['hardware']));
    }

    expect(resource_path("images/verticals/{$vertical}.jpg"))->toBeFile();
})->with($verticals);

it('formats illustration amounts from integer cents', function () {
    // 40 bags of cement at KES 780 = KES 31,200.00.
    $this->get('/en/solutions/hardware')->assertSeeInOrder(['Cement, 50 kg', '40 bags', 'KES 31,200.00']);
});

it('links every business type from the home page, the footer and the other solution pages', function () {
    $home = $this->get('/en');

    foreach (['restaurants', 'bars', 'salons', 'shops', 'supermarkets', 'hardware', 'pharmacy', 'liquor'] as $vertical) {
        $home->assertSee('href="'.url("/en/solutions/{$vertical}").'"', false);
    }

    $this->get('/sw/solutions/bars')
        ->assertSee('Biashara nyingine tunazohudumia')
        ->assertSee('href="'.url('/sw/solutions/restaurants').'"', false)
        ->assertSee('href="'.url('/sw/solutions/liquor').'"', false);
});

it('keeps the language switcher on the same solution page', function () {
    $this->get('/en/solutions/pharmacy')->assertSee('href="'.url('/sw/solutions/pharmacy').'"', false);
});

it('returns 404 for business types without a page', function () {
    $this->get('/en/solutions/casinos')->assertNotFound();
});
