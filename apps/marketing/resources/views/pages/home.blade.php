@php
    use VendaPOS\Core\Enums\VatCategory;
    use VendaPOS\Core\ValueObjects\Money;

    $demoUrl = Route::has('demo') ? route('demo') : '#get-started';

    // Illustrative order on the hero till. Prices are VAT-inclusive, as on a Kenyan menu.
    $tillItems = ['nyama' => 185_000, 'kuku' => 140_000, 'juice' => 45_000, 'beer' => 35_000];
    $total = Money::fromCents(array_sum($tillItems));
    $rate = VatCategory::Standard->rate();
    $vat = Money::fromCents(intdiv($total->cents * $rate + intdiv(10_000 + $rate, 2), 10_000 + $rate));
    $net = $total->subtract($vat);

    $verticalIcons = [
        'restaurants' => 'utensils', 'bars' => 'beer', 'salons' => 'scissors', 'shops' => 'store',
        'supermarkets' => 'shopping-cart', 'hardware' => 'hammer', 'pharmacy' => 'pill', 'liquor' => 'package',
    ];
    $pillarIcons = [
        'offline' => 'wifi-off', 'mpesa' => 'smartphone', 'etims' => 'receipt-text',
        'branches' => 'network', 'stock' => 'warehouse', 'staff' => 'id-card',
    ];
    $trustIcons = ['isolation' => 'database', 'durable' => 'shield-check', 'idempotent' => 'refresh-cw', 'cards' => 'credit-card'];
@endphp

<x-layouts.site :title="__('home.meta.title')" :description="__('home.meta.description')">

    {{-- Hero --}}
    <section class="aura overflow-hidden">
        <div class="container-page grid items-center gap-12 py-12 lg:grid-cols-[1.1fr_1fr] lg:py-24">
            <div>
                <ul class="flex flex-wrap gap-2">
                    <li><x-badge variant="success" dot>{{ __('home.hero.chips.offline') }}</x-badge></li>
                    <li><x-badge variant="coral">{{ __('home.hero.chips.etims') }}</x-badge></li>
                    <li><x-badge variant="mpesa">{{ __('home.hero.chips.mpesa') }}</x-badge></li>
                </ul>

                <h1 class="mt-6 text-display font-bold text-balance lg:text-display-lg">{{ __('home.hero.title') }}</h1>
                <p class="mt-6 max-w-xl text-lg text-muted text-pretty">{{ __('home.hero.lead') }}</p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <x-button :href="$demoUrl" size="lg" icon="arrow-right">{{ __('home.hero.primary_cta') }}</x-button>
                    <x-button href="#pricing" variant="secondary" size="lg">{{ __('home.hero.secondary_cta') }}</x-button>
                </div>
            </div>

            {{-- Till illustration: plain HTML so it stays sharp and weighs almost nothing. --}}
            <figure role="img" aria-label="{{ __('home.till.label') }}" class="rounded-card border border-line bg-surface shadow-float">
                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3 text-xs text-muted">
                    <div>
                        <p class="font-semibold text-ink">{{ __('home.till.location') }}</p>
                        <p>{{ __('home.till.cashier') }}</p>
                    </div>
                    <x-badge variant="success" dot>{{ __('home.till.online') }}</x-badge>
                </div>

                <div class="p-4">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold">{{ __('home.till.order') }}</p>
                        <x-badge variant="coral">
                            <x-icon name="badge-check" class="size-3.5" />{{ __('home.till.etims') }}
                        </x-badge>
                    </div>

                    <ul class="mt-3 divide-y divide-line text-sm">
                        @foreach ($tillItems as $key => $cents)
                            <li class="flex justify-between gap-4 py-2.5">
                                <span>1 × {{ __("home.till.items.{$key}") }}</span>
                                <span class="tabular shrink-0">{{ Money::fromCents($cents)->format() }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="mt-3 space-y-1.5 border-t border-line pt-3 text-sm">
                        <div class="flex justify-between text-muted">
                            <dt>{{ __('home.till.net') }}</dt>
                            <dd class="tabular">{{ $net->format() }}</dd>
                        </div>
                        <div class="flex justify-between text-muted">
                            <dt>{{ __('home.till.vat') }}</dt>
                            <dd class="tabular">{{ $vat->format() }}</dd>
                        </div>
                        <div class="flex justify-between pt-1 text-lg font-bold">
                            <dt>{{ __('home.till.total') }}</dt>
                            <dd class="tabular">{{ $total->format() }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 space-y-2" aria-hidden="true">
                        <div class="flex min-h-12 items-center justify-center gap-2 rounded-card bg-mpesa font-semibold text-canvas">
                            <x-icon name="smartphone" />
                            {{ __('home.till.pay_mpesa', ['amount' => $total->format()]) }}
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm text-muted">
                            <div class="flex min-h-11 items-center justify-center gap-2 rounded-card border border-line">
                                <x-icon name="credit-card" class="size-4" />{{ __('home.till.card') }}
                            </div>
                            <div class="flex min-h-11 items-center justify-center gap-2 rounded-card border border-line">
                                <x-icon name="banknote" class="size-4" />{{ __('home.till.cash') }}
                            </div>
                        </div>
                    </div>
                </div>
            </figure>
        </div>
    </section>

    {{-- Verticals --}}
    <section class="border-y border-line bg-surface py-16 lg:py-24">
        <div class="container-page">
            <x-section-heading :eyebrow="__('home.verticals.eyebrow')" :title="__('home.verticals.title')" :lead="__('home.verticals.lead')" />

            <ul class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (__('home.verticals.items') as $key => $vertical)
                    <li class="overflow-hidden rounded-card border border-line bg-canvas">
                        <x-picture :name="'verticals/'.$key" :alt="$vertical['alt']" :icon="$verticalIcons[$key]"
                                   sizes="(min-width: 1024px) 280px, (min-width: 640px) 50vw, 100vw" />
                        <div class="p-5">
                            <h3 class="flex items-center gap-2 font-semibold">
                                <x-icon :name="$verticalIcons[$key]" class="size-5 text-coral" />
                                {{ $vertical['title'] }}
                            </h3>
                            <p class="mt-2 text-sm text-muted">{{ $vertical['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Pillars --}}
    <section class="py-16 lg:py-24">
        <div class="container-page">
            <x-section-heading :eyebrow="__('home.pillars.eyebrow')" :title="__('home.pillars.title')" />

            <ul class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach (__('home.pillars.items') as $key => $pillar)
                    <li>
                        <x-card class="h-full">
                            <span class="flex size-11 items-center justify-center rounded-control bg-coral/10 text-coral">
                                <x-icon :name="$pillarIcons[$key]" class="size-6" />
                            </span>
                            <h3 class="mt-5 text-xl font-semibold">{{ $pillar['title'] }}</h3>
                            <p class="mt-3 text-muted">{{ $pillar['body'] }}</p>
                        </x-card>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Getting started --}}
    <section class="border-y border-line bg-surface py-16 lg:py-24">
        <div class="container-page grid items-center gap-12 lg:grid-cols-2">
            <x-picture name="onboarding/training" :alt="__('home.steps.photo_alt')" icon="users"
                       class="rounded-card" sizes="(min-width: 1024px) 568px, 100vw" />

            <div>
                <x-section-heading :eyebrow="__('home.steps.eyebrow')" :title="__('home.steps.title')" align="start" />

                <ol class="mt-8 space-y-6">
                    @foreach (__('home.steps.items') as $step)
                        <li class="flex gap-4">
                            <span class="tabular flex size-10 shrink-0 items-center justify-center rounded-full border border-coral/40 font-bold text-coral">
                                {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold">{{ $step['title'] }}</h3>
                                <p class="mt-1 text-muted">{{ $step['body'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    {{-- Reliability and security (paper surface, per DESIGN.md) --}}
    <section class="bg-paper py-16 text-canvas lg:py-24">
        <div class="container-page">
            <div class="mx-auto max-w-3xl text-center">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-paper-accent">{{ __('home.trust.eyebrow') }}</p>
                <h2 class="text-headline font-bold text-balance lg:text-headline-lg">{{ __('home.trust.title') }}</h2>
            </div>

            <ul class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (__('home.trust.items') as $key => $item)
                    <li class="rounded-card border border-paper-line bg-white p-6">
                        <x-icon :name="$trustIcons[$key]" class="size-7 text-paper-accent" />
                        <h3 class="mt-4 font-semibold">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm text-paper-muted">{{ $item['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Headline figures (placeholders until confirmed) --}}
    <section class="py-16" aria-labelledby="stats-title">
        <div class="container-page">
            <h2 id="stats-title" class="sr-only">{{ __('home.stats.title') }}</h2>
            <dl class="grid grid-cols-2 gap-8 lg:grid-cols-4">
                @foreach (config('marketing.stats') as $key => $stat)
                    <div class="flex flex-col-reverse items-center text-center">
                        <dt class="mt-2 text-sm text-muted">{{ __("home.stats.items.{$key}") }}</dt>
                        <dd class="tabular text-3xl font-bold text-coral lg:text-4xl">
                            <x-placeholder :confirmed="$stat['confirmed']">{{ $stat['value'] }}</x-placeholder>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- Pricing teaser --}}
    <section id="pricing" class="scroll-mt-20 border-t border-line bg-surface py-16 lg:py-24">
        <div class="container-page">
            <x-section-heading :eyebrow="__('home.pricing.eyebrow')" :title="__('home.pricing.title')" :lead="__('home.pricing.lead')" />

            <ul class="mt-12 grid gap-6 lg:grid-cols-3">
                @foreach (config('marketing.plans') as $key => $plan)
                    <li @class([
                        'relative flex flex-col rounded-card border bg-canvas p-6',
                        'border-coral shadow-glow' => $plan['popular'],
                        'border-line' => ! $plan['popular'],
                    ])>
                        @if ($plan['popular'])
                            <x-badge variant="coral" class="absolute -top-3 left-6 bg-canvas">{{ __('home.pricing.popular') }}</x-badge>
                        @endif

                        <h3 class="text-xl font-semibold">{{ __("home.pricing.plans.{$key}.name") }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ __("home.pricing.plans.{$key}.summary") }}</p>

                        <p class="mt-6 flex flex-wrap items-baseline gap-x-2">
                            @if ($plan['monthly_cents'] === null)
                                <span class="text-3xl font-bold">{{ __('home.pricing.custom') }}</span>
                            @else
                                <span class="tabular text-3xl font-bold">
                                    <x-placeholder :confirmed="config('marketing.prices_confirmed')">KES {{ number_format(intdiv($plan['monthly_cents'], 100)) }}</x-placeholder>
                                </span>
                                <span class="text-sm text-muted">{{ __('home.pricing.per_month') }}</span>
                            @endif
                        </p>

                        <ul class="mt-6 flex-1 space-y-3 text-sm">
                            @foreach (__("home.pricing.plans.{$key}.features") as $feature)
                                <li class="flex gap-2">
                                    <x-icon name="check" class="size-5 text-success" />{{ $feature }}
                                </li>
                            @endforeach
                        </ul>

                        <x-button :href="$demoUrl" :variant="$plan['popular'] ? 'primary' : 'secondary'" class="mt-8">
                            {{ __('home.pricing.cta') }}
                        </x-button>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Closing call to action --}}
    <section id="get-started" class="scroll-mt-20 py-16 lg:py-24">
        <div class="container-page">
            <div class="aura grid items-center gap-10 overflow-hidden rounded-card border border-line bg-surface lg:grid-cols-2">
                <div class="p-8 lg:p-12">
                    <h2 class="text-headline font-bold text-balance lg:text-headline-lg">{{ __('home.cta.title') }}</h2>
                    <p class="mt-4 text-lg text-muted">{{ __('home.cta.lead') }}</p>
                    <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center">
                        <x-button :href="$demoUrl" size="lg" icon="arrow-right">{{ __('home.cta.button') }}</x-button>
                        <p class="flex flex-wrap items-center gap-2 text-sm text-muted">
                            {{ __('home.cta.call') }}
                            <x-placeholder :confirmed="config('marketing.contact.confirmed')">{{ config('marketing.contact.phone') }}</x-placeholder>
                        </p>
                    </div>
                </div>
                <x-picture name="cta/owner" :alt="__('home.cta.photo_alt')" icon="trending-up"
                           ratio="4/3" sizes="(min-width: 1024px) 600px, 100vw" class="h-full" />
            </div>
        </div>
    </section>

</x-layouts.site>
