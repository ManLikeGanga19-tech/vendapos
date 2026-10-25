@php
    // Floor plan illustration: table => [state, guests].
    $tables = [
        '01' => ['seated', 2], '02' => ['free', 0], '03' => ['mains', 4], '04' => ['bill', 3],
        '05' => ['seated', 6], '06' => ['free', 0], '07' => ['mains', 2], '08' => ['seated', 4],
        '09' => ['bill', 2], '10' => ['free', 0], '11' => ['mains', 5], '12' => ['free', 0],
    ];
    $stateStyles = [
        'seated' => 'border-line-strong bg-elevated text-ink',
        'mains' => 'border-pending/50 bg-pending/10 text-pending',
        'bill' => 'border-coral/60 bg-coral/10 text-coral',
        'free' => 'border-dashed border-line text-subtle',
    ];
    $occupied = count(array_filter($tables, fn (array $t) => $t[0] !== 'free'));

    $problemIcons = ['kitchen' => ['chef-hat', null], 'payments' => ['smartphone', 'mpesa'], 'closing' => ['receipt-text', 'kra']];
    $featureIcons = [
        'floor' => ['layout-grid', null], 'courses' => ['timer', null], 'modifiers' => ['utensils', null], 'kds' => ['chef-hat', null],
        'split' => ['hand-coins', null], 'tips' => ['banknote', null], 'etims' => ['receipt-text', 'kra'], 'offline' => ['cloud-off', null],
    ];
    $hardwareIcons = ['till' => 'monitor', 'printer' => 'printer', 'receipts' => 'banknote'];
@endphp

<x-layouts.site :title="__('restaurants.meta.title')" :description="__('restaurants.meta.description')">

    <nav class="border-b border-line" aria-label="{{ __('common.breadcrumb') }}">
        <ol class="container-page flex items-center gap-2 py-3 text-sm text-muted">
            <li>{{ __('restaurants.breadcrumb.solutions') }}</li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-ink">{{ __('restaurants.breadcrumb.current') }}</li>
        </ol>
    </nav>

    {{-- Hero --}}
    <section class="py-12 lg:py-20">
        <div class="container-page grid items-center gap-12 lg:grid-cols-[1.05fr_1fr]">
            <div>
                <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-coral">
                    <x-icon name="utensils" class="size-4" />{{ __('restaurants.hero.eyebrow') }}
                </p>
                <h1 class="text-display font-bold text-balance lg:text-display-lg">{{ __('restaurants.hero.title') }}</h1>
                <p class="mt-6 max-w-xl text-lg text-muted text-pretty">{{ __('restaurants.hero.lead') }}</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <x-button :href="route('demo', ['type' => 'restaurant'])" size="lg" icon="arrow-right">{{ __('restaurants.hero.primary_cta') }}</x-button>
                    <x-button :href="route('pricing')" variant="secondary" size="lg">{{ __('restaurants.hero.secondary_cta') }}</x-button>
                </div>
            </div>

            <x-picture name="verticals/restaurants" :alt="__('restaurants.hero.photo_alt')" icon="utensils" eager
                       sizes="(min-width: 1024px) 560px, 100vw" />
        </div>
    </section>

    {{-- Floor plan illustration --}}
    <section class="border-y border-line bg-surface py-14">
        <div class="container-page">
            <figure role="img" aria-label="{{ __('restaurants.floor.label') }}" class="reveal border border-line bg-canvas p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 font-semibold"><x-icon name="layout-grid" class="size-5 text-coral" />{{ __('restaurants.floor.title') }}</p>
                    <x-badge variant="success" dot>{{ __('restaurants.floor.occupied', ['count' => $occupied, 'total' => count($tables)]) }}</x-badge>
                </div>

                <ul class="mt-6 grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach ($tables as $number => [$state, $guests])
                        <li class="flex min-h-24 flex-col justify-between border p-3 {{ $stateStyles[$state] }}">
                            <span class="text-lg font-bold">T{{ $number }}</span>
                            <span class="text-xs">
                                {{ __("restaurants.floor.states.{$state}") }}
                                @if ($guests)
                                    <span class="block text-subtle">{{ __('restaurants.floor.guests', ['count' => $guests]) }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>

                <ul class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-xs text-muted" aria-hidden="true">
                    @foreach ($stateStyles as $state => $style)
                        <li class="flex items-center gap-2"><span class="size-3 border {{ $style }}"></span>{{ __("restaurants.floor.states.{$state}") }}</li>
                    @endforeach
                </ul>
            </figure>
        </div>
    </section>

    {{-- Problems and fixes --}}
    <section class="py-16 lg:py-24">
        <div class="container-page">
            <x-section-heading :eyebrow="__('restaurants.problems.eyebrow')" :title="__('restaurants.problems.title')" />

            <ol class="mt-12 grid gap-6 lg:grid-cols-3">
                @foreach (__('restaurants.problems.items') as $key => $item)
                    <li>
                        <x-card class="flex h-full flex-col">
                            <div class="flex items-center gap-3">
                                <x-feature-icon :icon="$problemIcons[$key][0]" :brand="$problemIcons[$key][1]" />
                                <span class="tabular text-sm font-semibold text-subtle">0{{ $loop->iteration }}</span>
                            </div>
                            <h3 class="mt-5 text-xl font-semibold">{{ $item['title'] }}</h3>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-subtle">{{ __('restaurants.problems.problem') }}</p>
                            <p class="mt-1 text-muted">{{ $item['problem'] }}</p>
                            <p class="mt-4 border-t border-line pt-4 text-xs font-semibold uppercase tracking-widest text-success">{{ __('restaurants.problems.fix') }}</p>
                            <p class="mt-1">{{ $item['fix'] }}</p>
                        </x-card>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Feature grid --}}
    <section class="border-t border-line bg-surface py-16 lg:py-24">
        <div class="container-page">
            <x-section-heading :eyebrow="__('restaurants.features.eyebrow')" :title="__('restaurants.features.title')" />

            <ul class="reveal-children mt-12 grid gap-px border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">
                @foreach (__('restaurants.features.items') as $key => $feature)
                    <li class="bg-canvas p-6">
                        <x-feature-icon :icon="$featureIcons[$key][0]" :brand="$featureIcons[$key][1]" />
                        <h3 class="mt-4 font-semibold">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm text-muted">{{ $feature['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Kitchen display --}}
    <section class="py-16 lg:py-24">
        <div class="container-page grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-coral">{{ __('restaurants.kitchen.eyebrow') }}</p>
                <h2 class="text-headline font-bold text-balance lg:text-headline-lg">{{ __('restaurants.kitchen.title') }}</h2>
                <p class="mt-4 text-lg text-muted">{{ __('restaurants.kitchen.body') }}</p>

                <h3 class="mt-8 font-semibold">{{ __('restaurants.hardware.title') }}</h3>
                <ul class="mt-4 space-y-3">
                    @foreach (__('restaurants.hardware.items') as $key => $item)
                        <li class="flex items-center gap-3 text-sm">
                            <x-feature-icon :icon="$hardwareIcons[$key]" />{{ $item }}
                        </li>
                    @endforeach
                </ul>
            </div>
            <x-illustrations.kds />
        </div>
    </section>

    <x-closing-cta :title="__('restaurants.cta.title')" :lead="__('restaurants.cta.lead')" :button="__('restaurants.cta.button')"
                   :demo-url="route('demo', ['type' => 'restaurant'])"
                   photo="verticals/bars" :photo-alt="__('restaurants.cta.photo_alt')" photo-icon="beer" />

    <x-solutions-nav current="restaurants" />

</x-layouts.site>
