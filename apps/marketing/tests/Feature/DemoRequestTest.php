<?php

use App\Http\Requests\StoreDemoRequest;
use App\Models\DemoRequest;
use App\Notifications\DemoRequestReceived;
use App\Notifications\NewDemoRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

/**
 * A valid submission whose form token was issued long enough ago to pass the time trap.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function demoPayload(array $overrides = []): array
{
    $token = StoreDemoRequest::issueFormToken();
    test()->travel(10)->seconds();

    return array_merge([
        'request_id' => (string) Str::uuid(),
        'form_token' => $token,
        'name' => 'Wanjiku Kamau',
        'email' => 'wanjiku@mamambogarestaurant.co.ke',
        'phone' => '0712 345 678',
        'business_name' => 'Mama Mboga Restaurant',
        'business_type' => 'restaurant',
        'outlets' => '2-5',
        'contact_window' => 'morning',
        'message' => 'Three tills, want to go live in January.',
        'consent' => '1',
    ], $overrides);
}

it('renders the demo form in both languages', function (string $locale, string $title) {
    $response = $this->get("/{$locale}/demo")
        ->assertOk()
        ->assertSee($title)
        ->assertSee('name="request_id"', false)
        ->assertSee('name="_token"', false);

    // No untranslated keys such as "demo.form.title" leaked into the page.
    expect($response->getContent())->not->toMatch('/\b(demo|nav|footer|common)\.[a-z_]+/');
})->with([
    ['en', 'Request your demo'],
    ['sw', 'Omba onyesho lako'],
]);

it('stores the lead with a normalised phone and queues both emails after commit', function () {
    $payload = demoPayload();

    $this->post('/sw/demo', $payload)->assertRedirect('/sw/demo/thanks');

    $lead = DemoRequest::sole();
    expect($lead->id)->toBe($payload['request_id'])
        ->and($lead->phone)->toBe('+254712345678')
        ->and($lead->locale)->toBe('sw')
        ->and($lead->consented_at)->not->toBeNull();

    Notification::assertSentTo($lead, DemoRequestReceived::class, fn ($n) => $n->afterCommit === true);
    Notification::assertSentTo(
        new AnonymousNotifiable,
        NewDemoRequest::class,
        fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === config('marketing.leads.inbox') && $n->afterCommit === true,
    );
});

it('sends the visitor acknowledgement in their language', function () {
    $this->post('/sw/demo', demoPayload());

    expect(DemoRequest::sole()->preferredLocale())->toBe('sw');
    Notification::assertSentTo(DemoRequest::sole(), DemoRequestReceived::class, function ($n, $channels, $notifiable) {
        app()->setLocale($notifiable->preferredLocale());

        return $n->toMail($notifiable)->subject === 'Tumepokea ombi lako la onyesho la VendaPOS';
    });
});

it('is idempotent when the same form is submitted twice', function () {
    $payload = demoPayload();

    $this->post('/en/demo', $payload)->assertRedirect('/en/demo/thanks');
    $this->post('/en/demo', $payload)->assertRedirect('/en/demo/thanks');

    expect(DemoRequest::count())->toBe(1);
    Notification::assertSentTimes(DemoRequestReceived::class, 1);
});

it('quietly drops honeypot submissions', function () {
    $this->post('/en/demo', demoPayload([StoreDemoRequest::HONEYPOT => 'https://spam.example']))
        ->assertRedirect('/en/demo/thanks');

    expect(DemoRequest::count())->toBe(0);
    Notification::assertNothingSent();
});

it('quietly drops forms submitted faster than a person could type', function () {
    $payload = demoPayload();
    $payload['form_token'] = StoreDemoRequest::issueFormToken(); // issued "just now"

    $this->post('/en/demo', $payload)->assertRedirect('/en/demo/thanks');

    expect(DemoRequest::count())->toBe(0);
});

it('rejects tampered form tokens and unknown fields', function () {
    $this->from('/en/demo')
        ->post('/en/demo', demoPayload(['form_token' => 'not-a-token', 'tenant_id' => 'acme']))
        ->assertRedirect('/en/demo')
        ->assertSessionHasErrors(['form_token', 'form']);

    expect(DemoRequest::count())->toBe(0);
});

it('validates input with Swahili messages on the Swahili form', function () {
    $this->from('/sw/demo')
        ->post('/sw/demo', demoPayload(['name' => '', 'phone' => '12345', 'business_type' => 'casino', 'consent' => null]))
        ->assertRedirect('/sw/demo')
        ->assertSessionHasErrors([
            'name' => 'Jina kamili inahitajika.',
            'phone' => 'Weka namba ya simu sahihi, kama 0712 345 678 au +256 772 123 456.',
            'business_type' => 'Aina ya biashara: tafadhali chagua mojawapo ya chaguo.',
            'consent' => 'Tafadhali kubali ili tuweze kuwasiliana nawe kuhusu onyesho lako.',
        ]);
});

it('shows errors next to the fields and keeps what the visitor typed', function () {
    $this->from('/en/demo')->followingRedirects()
        ->post('/en/demo', demoPayload(['email' => 'not-an-email']))
        ->assertSee('Please check the highlighted fields.')
        ->assertSee('Enter a valid email address')
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('value="Mama Mboga Restaurant"', false);
});

it('rate limits submissions per IP', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/en/demo', demoPayload())->assertRedirect();
    }

    $this->post('/en/demo', demoPayload())->assertTooManyRequests();
});

it('links the header and home page calls to action to the demo form', function () {
    $this->get('/sw')->assertSee('href="'.url('/sw/demo').'"', false);
});

it('pre-selects the business type when coming from a solution page', function () {
    $this->get('/en/demo?type=salon')
        ->assertOk()
        ->assertSee('<option value="salon" selected', false);
});

it('ignores an unknown business type or plan in the link', function () {
    $response = $this->get('/en/demo?type=casino&plan=platinum')->assertOk();

    expect($response->getContent())->not->toContain(' selected')
        ->not->toContain('name="plan_interest"');
});

it('shows the plan the visitor picked and stores it with the lead', function (string $locale, string $note) {
    $this->get("/{$locale}/demo?plan=business")
        ->assertOk()
        ->assertSee($note)
        ->assertSee('name="plan_interest" value="business"', false);

    $this->post("/{$locale}/demo", demoPayload(['plan_interest' => 'business']))->assertRedirect();

    expect(DemoRequest::sole()->plan_interest)->toBe('business');
    Notification::assertSentTo(new AnonymousNotifiable, NewDemoRequest::class,
        fn (NewDemoRequest $n) => collect($n->toMail(new AnonymousNotifiable)->introLines)->contains(fn ($line) => str_contains($line, __('plans.business.name'))));
})->with([
    ['en', 'You’re asking about the'],
    ['sw', 'Unauliza kuhusu mpango wa'],
]);

it('rejects a plan that does not exist', function () {
    $this->post('/en/demo', demoPayload(['plan_interest' => 'platinum']))->assertSessionHasErrors('plan_interest');

    expect(DemoRequest::count())->toBe(0);
});

it('links solution pages and pricing cards to a pre-filled demo form', function () {
    $this->get('/en/solutions/salons')->assertSee('href="'.url('/en/demo?type=salon').'"', false);
    $this->get('/en/solutions/restaurants')->assertSee('href="'.url('/en/demo?type=restaurant').'"', false);
    $this->get('/sw/pricing')->assertSee('href="'.url('/sw/demo?plan=business').'"', false);
});
