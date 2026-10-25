@php
    use VendaPOS\Core\ValueObjects\Money;

    /** @var string $vertical */
    $config = config("marketing.solutions.{$vertical}");
    $copy = __("solutions.{$vertical}");
    $common = __('solutions.common');
    $coreIcons = ['mpesa' => ['smartphone', 'mpesa'], 'etims' => ['receipt-text', 'kra'], 'offline' => ['cloud-off', null]];
@endphp

<x-layouts.site :title="$copy['meta']['title']" :description="$copy['meta']['description']">

    <nav class="border-b border-line" aria-label="{{ __('common.breadcrumb') }}">
        <ol class="container-page flex items-center gap-2 py-3 text-sm text-muted">
            <li>{{ $common['breadcrumb'] }}</li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-ink">{{ $copy['name'] }}</li>
        </ol>
    </nav>

    {{-- Hero --}}
    <section class="py-12 lg:py-20">
        <div class="container-page grid items-center gap-12 lg:grid-cols-[1.05fr_1fr]">
            <div>
                <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-coral">
                    <x-icon :name="$config['icon']" class="size-4" />{{ $copy['hero']['eyebrow'] }}
                </p>
                <h1 class="text-display font-bold text-balance lg:text-display-lg">{{ $copy['hero']['title'] }}</h1>
                <p class="mt-6 max-w-xl text-lg text-muted text-pretty">{{ $copy['hero']['lead'] }}</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <x-button :href="route('demo')" size="lg" icon="arrow-right">{{ $copy['hero']['primary_cta'] }}</x-button>
                    <x-button :href="route('pricing')" variant="secondary" size="lg">{{ $common['secondary_cta'] }}</x-button>
                </div>
            </div>

            <x-picture :name="'verticals/'.$vertical" :alt="$copy['hero']['photo_alt']" :icon="$config['icon']" eager
                       sizes="(min-width: 1024px) 560px, 100vw" />
        </div>
    </section>

    {{-- Screen illustration for this business type --}}
    <section class="border-y border-line bg-surface py-14">
        <div class="container-page">
            <figure role="img" aria-label="{{ $copy['panel']['label'] }}" class="reveal mx-auto max-w-3xl border border-line bg-canvas">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <p class="flex items-center gap-2 font-semibold">
                        <x-icon :name="$config['panel']['icon']" class="size-5 text-coral" />{{ $copy['panel']['title'] }}
                    </p>
                    <x-badge :variant="$config['panel']['badge']">{{ $copy['panel']['badge'] }}</x-badge>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($config['panel']['rows'] as $row => $data)
                        @php($text = $copy['panel']['rows'][$row])
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ $text['title'] }}</p>
                                <p class="text-sm text-muted">{{ $text['meta'] }}</p>
                            </div>
                            @if ($data['state'] && isset($text['status']))
                                <x-badge :variant="$data['state']">{{ $text['status'] }}</x-badge>
                            @endif
                            @if ($data['cents'] !== null)
                                <span class="tabular w-32 text-right font-semibold">{{ Money::fromCents($data['cents'])->format() }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </figure>
        </div>
    </section>

    {{-- Problems and fixes --}}
    <section class="py-16 lg:py-24">
        <div class="container-page">
            <x-section-heading :eyebrow="$common['problems_eyebrow']" :title="$copy['problems']['title']" />

            <ol class="mt-12 grid gap-6 lg:grid-cols-3">
                @foreach ($copy['problems']['items'] as $key => $item)
                    <li>
                        <x-card class="flex h-full flex-col">
                            <div class="flex items-center gap-3">
                                <x-feature-icon :icon="$config['problems'][$key]" />
                                <span class="tabular text-sm font-semibold text-subtle">0{{ $loop->iteration }}</span>
                            </div>
                            <h3 class="mt-5 text-xl font-semibold">{{ $item['title'] }}</h3>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-subtle">{{ $common['problem'] }}</p>
                            <p class="mt-1 text-muted">{{ $item['problem'] }}</p>
                            <p class="mt-4 border-t border-line pt-4 text-xs font-semibold uppercase tracking-widest text-success">{{ $common['fix'] }}</p>
                            <p class="mt-1">{{ $item['fix'] }}</p>
                        </x-card>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Features, then the shared core --}}
    <section class="border-t border-line bg-surface py-16 lg:py-24">
        <div class="container-page">
            <x-section-heading :eyebrow="$common['features_eyebrow']" :title="$copy['features']['title']" />

            <ul class="reveal-children mt-12 grid gap-px border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($copy['features']['items'] as $key => $feature)
                    <li class="bg-canvas p-6">
                        <x-feature-icon :icon="$config['features'][$key]" />
                        <h3 class="mt-4 font-semibold">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm text-muted">{{ $feature['body'] }}</p>
                    </li>
                @endforeach
            </ul>

            <h3 class="mt-14 text-center font-semibold">{{ $common['core_title'] }}</h3>
            <ul class="reveal-children mt-6 grid gap-6 md:grid-cols-3">
                @foreach ($common['core'] as $key => $item)
                    <li class="flex gap-4 border border-line bg-canvas p-5">
                        <x-feature-icon :icon="$coreIcons[$key][0]" :brand="$coreIcons[$key][1]" />
                        <div>
                            <p class="font-semibold">{{ $item['title'] }}</p>
                            <p class="mt-1 text-sm text-muted">{{ $item['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="reveal mt-14 border border-line bg-canvas p-6">
                <h3 class="font-semibold">{{ $common['hardware_title'] }}</h3>
                <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($copy['hardware'] as $key => $item)
                        <li class="flex items-center gap-3 text-sm">
                            <x-feature-icon :icon="$config['hardware'][$key]" />{{ $item }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <x-closing-cta :title="$copy['cta']['title']" :lead="$copy['cta']['lead']" :button="$copy['cta']['button']"
                   photo="cta/owner" :photo-alt="__('home.cta.photo_alt')" />

    <x-solutions-nav :current="$vertical" />

</x-layouts.site>
