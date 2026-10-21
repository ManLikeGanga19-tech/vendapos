<?php

use App\Support\ImageManifest;
use Illuminate\Support\Facades\File;

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

it('shows a photo with translated alt text for every vertical', function () {
    File::put($manifest = storage_path('framework/testing/manifest-'.uniqid().'.json'), json_encode(
        collect(__('home.verticals.items'))->mapWithKeys(fn ($v, string $key) => [
            "verticals/{$key}" => ['width' => 1600, 'height' => 1200, 'widths' => [480, 1600], 'formats' => ['avif', 'webp']],
        ]),
    ));
    app()->instance(ImageManifest::class, new ImageManifest($manifest));

    $response = $this->get('/sw')->assertOk();

    foreach (__('home.verticals.items', [], 'sw') as $key => $vertical) {
        $response->assertSee(asset("images/verticals/{$key}-480.avif"), false)
            ->assertSee('alt="'.e($vertical['alt']).'"', false);
    }

    File::delete($manifest);
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

it('shows the official M-Pesa and KRA marks on the hero and feature cards', function () {
    $html = $this->get('/en')->getContent();

    expect(substr_count($html, asset('brand/mpesa.png')))->toBeGreaterThanOrEqual(3)
        ->and(substr_count($html, asset('brand/kra.png')))->toBeGreaterThanOrEqual(2);
});
