@php
    use VendaPOS\Core\Enums\VatCategory;
    use VendaPOS\Core\ValueObjects\Money;

    $p = 'product.sections';

    // Owner dashboard illustration: today's sales per Mombasa branch, in cents.
    $branchSales = ['cbd' => [18_642_000, 6, 0], 'nyali' => [12_431_000, 4, 0], 'diani' => [9_655_000, 3, 1]];
    $gross = Money::fromCents(array_sum(array_column($branchSales, 0)));
    $maxSales = max(array_column($branchSales, 0));
    $recentPayments = [['07•• ••• 214', 125_000, '14:02'], ['07•• ••• 873', 405_000, '13:58'], ['01•• ••• 506', 36_000, '13:55']];

    // Receipt illustration: all standard-rated, VAT-inclusive prices.
    $receiptItems = ['coffee' => 25_000, 'mandazi' => 6_000, 'water' => 5_000];
    $receiptTotal = Money::fromCents(array_sum($receiptItems));
    $rate = VatCategory::Standard->rate();
    $receiptVat = Money::fromCents(intdiv($receiptTotal->cents * $rate + intdiv(10_000 + $rate, 2), 10_000 + $rate));

    $sections = [
        'till' => ['icon' => 'monitor'],
        'payments' => ['icon' => 'smartphone', 'brand' => 'mpesa'],
        'etims' => ['icon' => 'receipt-text', 'brand' => 'kra'],
        'offline' => ['icon' => 'cloud-off'],
        'stock' => ['icon' => 'warehouse'],
        'branches' => ['icon' => 'network'],
        'staff' => ['icon' => 'id-card'],
        'kitchen' => ['icon' => 'chef-hat'],
    ];
    $syncIcons = ['till' => 'monitor', 'queue' => 'database', 'cloud' => 'server', 'kra' => 'receipt-text'];
    $hardwareIcons = ['computers' => 'monitor', 'printers' => 'printer', 'drawers' => 'banknote', 'scanners' => 'scan-barcode', 'kds' => 'chef-hat', 'phones' => 'smartphone'];
@endphp

<x-layouts.site :title="__('product.meta.title')" :description="__('product.meta.description')">

    {{-- Hero with an owner-dashboard illustration --}}
    <section class="py-12 lg:py-20">
        <div class="container-page grid items-center gap-12 lg:grid-cols-[1.05fr_1fr]">
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-coral">{{ __('product.hero.eyebrow') }}</p>
                <h1 class="text-display font-bold text-balance lg:text-display-lg">{{ __('product.hero.title') }}</h1>
                <p class="mt-6 max-w-xl text-lg text-muted text-pretty">{{ __('product.hero.lead') }}</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <x-button :href="route('demo')" size="lg" icon="arrow-right">{{ __('product.hero.primary_cta') }}</x-button>
                    <x-button :href="route('pricing')" variant="secondary" size="lg">{{ __('product.hero.secondary_cta') }}</x-button>
                </div>
            </div>

            <figure role="img" aria-label="{{ __('product.dashboard.label') }}" class="border border-line bg-surface shadow-float">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <p class="font-semibold">{{ __('product.dashboard.title') }}</p>
                    <x-badge variant="success" dot>{{ __('product.dashboard.live') }}</x-badge>
                </div>
                <div class="p-5">
                    <p class="text-sm text-muted">{{ __('product.dashboard.total') }}</p>
                    <p class="tabular mt-1 text-3xl font-bold">{{ $gross->format() }}</p>

                    <ul class="mt-6 space-y-4">
                        @foreach ($branchSales as $branch => [$cents, $tills, $syncing])
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="font-medium">{{ __("product.dashboard.branches.{$branch}") }}</span>
                                    <span class="tabular text-muted">{{ Money::fromCents($cents)->format() }}</span>
                                </div>
                                <div class="mt-2 h-2 bg-line">
                                    <div class="h-2 bg-coral" style="width: {{ intdiv($cents * 100, $maxSales) }}%"></div>
                                </div>
                                <p class="mt-1.5 flex items-center gap-2 text-xs text-subtle">
                                    {{ __('product.dashboard.tills', ['count' => $tills]) }}
                                    @if ($syncing)
                                        <x-badge variant="pending" class="py-0">{{ $syncing }} {{ __('product.dashboard.syncing') }}</x-badge>
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-6 text-xs font-semibold uppercase tracking-widest text-subtle">{{ __('product.dashboard.recent') }}</p>
                    <ul class="mt-2 divide-y divide-line text-sm">
                        @foreach ($recentPayments as [$phone, $cents, $time])
                            <li class="flex items-center gap-3 py-2.5">
                                <x-brand-mark name="mpesa" icon="smartphone" />
                                <span class="tabular flex-1 text-muted">{{ $phone }}</span>
                                <span class="tabular font-medium">{{ Money::fromCents($cents)->format() }}</span>
                                <span class="tabular w-12 text-right text-xs text-subtle">{{ $time }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </figure>
        </div>
    </section>

    {{-- Jump links --}}
    <nav class="border-y border-line bg-surface" aria-label="{{ __('product.jump') }}">
        <ul class="container-page flex gap-2 overflow-x-auto py-3">
            @foreach ($sections as $key => $section)
                <li class="shrink-0">
                    <a href="#{{ $key }}" class="btn min-h-10 border border-line px-4 text-sm font-medium text-muted hover:border-line-strong hover:text-ink">
                        {{ __("{$p}.{$key}.nav") }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    @foreach ($sections as $key => $section)
        @php($copy = __("{$p}.{$key}"))
        <x-feature-section :id="$key" :reverse="$loop->even" :icon="$section['icon']" :brand="$section['brand'] ?? null"
                           :eyebrow="$copy['eyebrow']" :title="$copy['title']" :body="$copy['body']" :points="$copy['points']"
                           :class="$loop->first ? '' : 'border-t border-line'">
            @switch($key)
                @case('payments')
                    <figure role="img" aria-label="{{ __('product.stk.label') }}" class="border border-line bg-surface p-6">
                        <div class="flex items-center gap-3">
                            <x-brand-mark name="mpesa" icon="smartphone" size="md" />
                            <p class="tabular font-semibold">{{ __('product.stk.amount', ['amount' => Money::fromCents(405_000)->format()]) }}</p>
                        </div>
                        <ol class="mt-6 space-y-4">
                            @foreach (__('product.stk.steps') as $step => $text)
                                <li class="flex items-center gap-3">
                                    <span @class([
                                        'flex size-8 shrink-0 items-center justify-center border',
                                        'border-success/40 bg-success/10 text-success' => $step === 'confirmed',
                                        'border-line text-muted' => $step !== 'confirmed',
                                    ])>
                                        <x-icon :name="$step === 'confirmed' ? 'check' : ($step === 'pin' ? 'lock' : 'send')" class="size-4" />
                                    </span>
                                    <span @class(['text-sm', 'font-semibold text-ink' => $step === 'confirmed', 'text-muted' => $step !== 'confirmed'])>{{ $text }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </figure>
                    @break

                @case('etims')
                    <figure role="img" aria-label="{{ __('product.receipt.label') }}" class="mx-auto max-w-sm border border-paper-line bg-paper p-6 font-mono text-sm text-canvas">
                        <p class="text-center font-bold">{{ __('product.receipt.business') }}</p>
                        <ul class="mt-4 space-y-1.5 border-y border-dashed border-paper-line py-3">
                            @foreach ($receiptItems as $item => $cents)
                                <li class="flex justify-between gap-4">
                                    <span>{{ __("product.receipt.items.{$item}") }}</span>
                                    <span class="tabular">{{ Money::fromCents($cents)->format() }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-3 flex justify-between text-paper-muted">
                            <span>{{ __('product.receipt.vat') }}</span><span class="tabular">{{ $receiptVat->format() }}</span>
                        </div>
                        <div class="mt-1 flex justify-between font-bold">
                            <span>{{ __('product.receipt.total') }}</span><span class="tabular">{{ $receiptTotal->format() }}</span>
                        </div>
                        <div class="mt-5 flex items-center gap-4 border-t border-dashed border-paper-line pt-4">
                            <x-icon name="qr-code" class="size-16 text-canvas" />
                            <div class="flex items-center gap-2 font-sans text-xs text-paper-muted">
                                <x-brand-mark name="kra" icon="receipt-text" size="md" class="ring-1 ring-paper-line" />
                                {{ __('product.receipt.scan') }}
                            </div>
                        </div>
                    </figure>
                    @break

                @case('offline')
                    <figure role="img" aria-label="{{ __('product.sync.label') }}" class="border border-line bg-surface p-6">
                        <ol class="grid gap-3 sm:grid-cols-4">
                            @foreach (__('product.sync.steps') as $step => $item)
                                <li class="relative flex items-center gap-3 border border-line bg-canvas p-4 sm:flex-col sm:text-center">
                                    @if ($step === 'kra')
                                        <x-brand-mark name="kra" icon="receipt-text" size="md" />
                                    @else
                                        <x-feature-icon :icon="$syncIcons[$step]" />
                                    @endif
                                    <div>
                                        <p class="text-sm font-semibold">{{ $item['title'] }}</p>
                                        <p class="mt-0.5 text-xs text-muted">{{ $item['body'] }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <x-badge variant="pending" dot>{{ __('home.hero.chips.offline') }}</x-badge>
                            <x-badge variant="success"><x-icon name="refresh-cw" class="size-3.5" />{{ __("{$p}.offline.points.batches") }}</x-badge>
                        </div>
                    </figure>
                    @break

                @case('kitchen')
                    <x-illustrations.kds />
                    @break

                @default
                    <x-picture :name="['till' => 'verticals/bars', 'stock' => 'verticals/hardware', 'branches' => 'verticals/supermarkets', 'staff' => 'verticals/pharmacy'][$key]"
                               :alt="$copy['photo_alt']" :icon="$section['icon']"
                               sizes="(min-width: 1024px) 560px, 100vw" />
            @endswitch
        </x-feature-section>
    @endforeach

    {{-- Hardware --}}
    <section class="border-t border-line bg-surface py-16 lg:py-24">
        <div class="container-page">
            <x-section-heading :eyebrow="__('product.hardware.eyebrow')" :title="__('product.hardware.title')" :lead="__('product.hardware.lead')" />
            <ul class="reveal-children mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (__('product.hardware.items') as $key => $item)
                    <li class="flex items-center gap-4 border border-line bg-canvas p-5">
                        <x-feature-icon :icon="$hardwareIcons[$key]" />
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <x-closing-cta :title="__('product.cta.title')" :lead="__('product.cta.lead')" :button="__('product.cta.button')"
                   photo="onboarding/training" :photo-alt="__('home.steps.photo_alt')" photo-icon="users" />

</x-layouts.site>
