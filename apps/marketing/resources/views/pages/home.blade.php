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

    $pillarIcons = [
        'offline' => 'wifi-off', 'mpesa' => 'smartphone', 'etims' => 'receipt-text',
        'branches' => 'network', 'stock' => 'warehouse', 'staff' => 'id-card',
    ];
    $partnerMarks = ['mpesa' => 'mpesa', 'etims' => 'kra'];
    $trustIcons = ['isolation' => 'database', 'durable' => 'shield-check', 'idempotent' => 'refresh-cw', 'cards' => 'credit-card'];
@endphp

<x-layouts.site :title="__('home.meta.title')" :description="__('home.meta.description')">

    {{-- Hero --}}
    <section class="overflow-hidden">
        <div class="container-page grid items-center gap-12 py-12 lg:grid-cols-[1.1fr_1fr] lg:py-24">
            <div>
                <ul class="flex flex-wrap gap-2">
                    <li><x-badge variant="success"><x-icon name="cloud-off" class="size-3.5" />{{ __('home.hero.chips.offline') }}</x-badge></li>
                    <li><x-badge variant="coral"><x-brand-mark name="kra" icon="receipt-text" />{{ __('home.hero.chips.etims') }}</x-badge></li>
                    <li><x-badge variant="mpesa"><x-brand-mark name="mpesa" icon="smartphone" />{{ __('home.hero.chips.mpesa') }}</x-badge></li>
                </ul>

                <h1 class="mt-6 text-display font-bold text-balance lg:text-display-lg">{{ __('home.hero.title') }}</h1>
                <p class="mt-6 max-w-xl text-lg text-muted text-pretty">{{ __('home.hero.lead') }}</p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <x-button :href="$demoUrl" size="lg" icon="arrow-right">{{ __('home.hero.primary_cta') }}</x-button>
                    <x-button :href="route('pricing')" variant="secondary" size="lg">{{ __('home.hero.secondary_cta') }}</x-button>
                </div>
            </div>

            {{-- Till illustration: plain HTML so it stays sharp and weighs almost nothing. --}}
            <figure role="img" aria-label="{{ __('home.till.label') }}" class="border border-line bg-surface shadow-float">
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
                        <div class="flex min-h-12 items-center justify-center gap-2 bg-mpesa font-semibold text-canvas">
                            <x-brand-mark name="mpesa" icon="smartphone" size="sm" class="size-6" />
                            {{ __('home.till.pay_mpesa', ['amount' => $total->format()]) }}
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm text-muted">
                            <div class="flex min-h-11 items-center justify-center gap-2 border border-line">
                                <x-icon name="credit-card" class="size-4" />{{ __('home.till.card') }}
                            </div>
                            <div class="flex min-h-11 items-center justify-center gap-2 border border-line">
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
                @foreach (array_keys(__('home.verticals.items')) as $key)
                    <li><x-solution-card :key="$key" /></li>
                @endforeach
            </ul>

            <p class="mt-10 text-center">
                <x-button :href="route('solutions')" variant="secondary" icon="arrow-right">{{ __('home.verticals.all') }}</x-button>
            </p>
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
                            <x-feature-icon :icon="$pillarIcons[$key]" :brand="$partnerMarks[$key] ?? null" size="lg" />
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
            <x-picture name="onboarding/training" :alt="__('home.steps.photo_alt')" icon="users" class="reveal"
                       sizes="(min-width: 1024px) 568px, 100vw" />

            <div>
                <x-section-heading :eyebrow="__('home.steps.eyebrow')" :title="__('home.steps.title')" align="start" />

                <ol class="reveal-children mt-8 space-y-6">
                    @foreach (__('home.steps.items') as $step)
                        <li class="flex gap-4">
                            <span class="tabular flex size-10 shrink-0 items-center justify-center border border-coral/40 font-bold text-coral">
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

            <ul class="reveal-children mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (__('home.trust.items') as $key => $item)
                    <li class="border border-paper-line bg-white p-6">
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
            <dl class="reveal-children grid grid-cols-2 gap-8 lg:grid-cols-4">
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
                    <x-plan-card :key="$key" :plan="$plan" :cta-url="route('demo', ['plan' => $key])" />
                @endforeach
            </ul>

            <p class="mt-10 text-center">
                <a href="{{ route('pricing') }}" class="inline-flex items-center gap-2 font-semibold text-coral hover:text-coral-hover">
                    {{ __('home.pricing.compare') }} <x-icon name="arrow-right" class="size-4" />
                </a>
            </p>
        </div>
    </section>

    <x-closing-cta :title="__('home.cta.title')" :lead="__('home.cta.lead')" :button="__('home.cta.button')"
                   photo="cta/owner" :photo-alt="__('home.cta.photo_alt')" />

</x-layouts.site>
