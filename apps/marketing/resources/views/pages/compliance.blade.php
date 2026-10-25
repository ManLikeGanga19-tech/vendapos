@php
    $certified = config('marketing.etims_certified');
    $stepIcons = ['sale' => 'database', 'send' => 'send', 'receipt' => 'qr-code', 'offline' => 'cloud-off', 'retry' => 'refresh-cw'];
    $paymentIcons = ['verify' => ['shield-check', 'mpesa'], 'query' => ['refresh-cw', null], 'idempotent' => ['badge-check', null], 'cards' => ['credit-card', null]];
    $dataIcons = ['isolation' => 'database', 'devices' => 'id-card', 'encryption' => 'lock', 'dpa' => 'shield-check', 'audit' => 'receipt-text', 'controller' => 'building-2'];
@endphp

<x-layouts.site :title="__('compliance.meta.title')" :description="__('compliance.meta.description')">

    {{-- Hero with certification status --}}
    <section class="py-12 lg:py-20">
        <div class="container-page grid items-center gap-12 lg:grid-cols-[1.2fr_1fr]">
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-coral">{{ __('compliance.hero.eyebrow') }}</p>
                <h1 class="text-display font-bold text-balance lg:text-display-lg">{{ __('compliance.hero.title') }}</h1>
                <p class="mt-6 max-w-xl text-lg text-muted text-pretty">{{ __('compliance.hero.lead') }}</p>
            </div>

            <div class="border border-line bg-surface p-6">
                <div class="flex items-center gap-4">
                    <x-brand-mark name="kra" icon="receipt-text" size="lg" />
                    <div>
                        <p class="text-sm text-muted">{{ __('compliance.status.title') }}</p>
                        <p class="mt-1 text-xl font-bold">
                            @if ($certified)
                                <span class="inline-flex items-center gap-2 text-success"><x-icon name="badge-check" />{{ __('compliance.status.certified') }}</span>
                            @else
                                <x-placeholder>{{ __('compliance.status.value') }}</x-placeholder>
                            @endif
                        </p>
                    </div>
                </div>
                @unless ($certified)
                    <p class="mt-4 border-t border-line pt-4 text-sm text-muted">{{ __('compliance.status.note') }}</p>
                @endunless
            </div>
        </div>
    </section>

    {{-- eTIMS flow --}}
    <section class="border-y border-line bg-surface py-16 lg:py-24">
        <div class="container-page">
            <x-section-heading :eyebrow="__('compliance.etims.eyebrow')" :title="__('compliance.etims.title')" />

            <ol class="reveal-children mt-12 grid gap-px border border-line bg-line sm:grid-cols-2 lg:grid-cols-5">
                @foreach (__('compliance.etims.steps') as $key => $step)
                    <li class="bg-canvas p-6">
                        <div class="flex items-center justify-between">
                            <x-feature-icon :icon="$stepIcons[$key]" />
                            <span class="tabular text-sm font-semibold text-subtle">0{{ $loop->iteration }}</span>
                        </div>
                        <h3 class="mt-4 font-semibold">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm text-muted">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>

            <div class="reveal mt-10 border border-line bg-canvas p-6">
                <h3 class="font-semibold">{{ __('compliance.vat.title') }}</h3>
                <ul class="mt-4 grid gap-4 md:grid-cols-3">
                    @foreach (__('compliance.vat.items') as $item)
                        <li class="flex gap-2 text-sm text-muted"><x-icon name="circle-check" class="size-5 text-success" />{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Payments --}}
    <section class="py-16 lg:py-24">
        <div class="container-page">
            <x-section-heading :eyebrow="__('compliance.payments.eyebrow')" :title="__('compliance.payments.title')" />

            <ul class="mt-12 grid gap-6 md:grid-cols-2">
                @foreach (__('compliance.payments.items') as $key => $item)
                    <li>
                        <x-card class="flex h-full gap-4">
                            <x-feature-icon :icon="$paymentIcons[$key][0]" :brand="$paymentIcons[$key][1]" />
                            <div>
                                <h3 class="font-semibold">{{ $item['title'] }}</h3>
                                <p class="mt-2 text-sm text-muted">{{ $item['body'] }}</p>
                            </div>
                        </x-card>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Data protection (paper surface) --}}
    <section class="bg-paper py-16 text-canvas lg:py-24">
        <div class="container-page">
            <div class="mx-auto max-w-3xl text-center">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-paper-accent">{{ __('compliance.data.eyebrow') }}</p>
                <h2 class="text-headline font-bold text-balance lg:text-headline-lg">{{ __('compliance.data.title') }}</h2>
            </div>

            <ul class="reveal-children mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (__('compliance.data.items') as $key => $item)
                    <li class="border border-paper-line bg-white p-6">
                        <x-icon :name="$dataIcons[$key]" class="size-7 text-paper-accent" />
                        <h3 class="mt-4 font-semibold">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm text-paper-muted">{{ $item['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <x-closing-cta :title="__('compliance.cta.title')" :lead="__('compliance.cta.lead')" :button="__('compliance.cta.button')"
                   photo="verticals/pharmacy" :photo-alt="__('compliance.cta.photo_alt')" photo-icon="shield-check" />

</x-layouts.site>
