@php
    use App\Support\Price;

    $demoUrl = route('demo');
    $plans = config('marketing.plans');
    $contentsConfirmed = config('marketing.plan_contents_confirmed');
    $includedIcons = [
        'offline' => ['cloud-off', null], 'etims' => ['receipt-text', 'kra'], 'mpesa' => ['smartphone', 'mpesa'],
        'isolation' => ['database', null], 'languages' => ['languages', null], 'updates' => ['refresh-cw', null],
    ];
@endphp

<x-layouts.site :title="__('pricing.meta.title')" :description="__('pricing.meta.description')">

    {{-- Plans, with a CSS-only monthly / yearly switch --}}
    <section class="group/billing py-12 lg:py-20">
        <div class="container-page">
            <div class="mx-auto max-w-3xl text-center">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-coral">{{ __('pricing.hero.eyebrow') }}</p>
                <h1 class="text-display font-bold text-balance lg:text-display-lg">{{ __('pricing.hero.title') }}</h1>
                <p class="mt-6 text-lg text-muted text-pretty">{{ __('pricing.hero.lead') }}</p>
            </div>

            <fieldset class="mx-auto mt-10 flex w-fit items-center gap-1 border border-line bg-surface p-1">
                <legend class="sr-only">{{ __('pricing.billing.label') }}</legend>
                <input type="radio" name="billing" id="billing-monthly" class="peer/monthly sr-only" checked>
                <label for="billing-monthly" class="cursor-pointer px-5 py-2.5 text-sm font-semibold text-muted transition-colors hover:text-ink peer-checked/monthly:bg-elevated peer-checked/monthly:text-ink peer-focus-visible/monthly:outline-2 peer-focus-visible/monthly:outline-coral">
                    {{ __('pricing.billing.monthly') }}
                </label>
                <input type="radio" name="billing" id="billing-annual" class="peer/annual sr-only">
                <label for="billing-annual" class="flex cursor-pointer items-center gap-2 px-5 py-2.5 text-sm font-semibold text-muted transition-colors hover:text-ink peer-checked/annual:bg-elevated peer-checked/annual:text-ink peer-focus-visible/annual:outline-2 peer-focus-visible/annual:outline-coral">
                    {{ __('pricing.billing.annual') }}
                    <x-badge variant="success" class="py-0.5">{{ __('pricing.billing.saving') }}</x-badge>
                </label>
            </fieldset>

            <ul class="mt-12 grid gap-6 lg:grid-cols-3">
                @foreach ($plans as $key => $plan)
                    <x-plan-card :key="$key" :plan="$plan" :cta-url="$demoUrl" annual />
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Included everywhere --}}
    <section class="border-y border-line bg-surface py-14">
        <div class="container-page">
            <h2 class="text-center text-xl font-semibold">{{ __('pricing.included.title') }}</h2>
            <ul class="mt-8 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (__('pricing.included.items') as $key => $item)
                    <li class="flex items-center gap-3">
                        <x-feature-icon :icon="$includedIcons[$key][0]" :brand="$includedIcons[$key][1]" />
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Comparison table --}}
    <section class="py-16 lg:py-24" aria-labelledby="compare-title">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 id="compare-title" class="text-headline font-bold lg:text-headline-lg">{{ __('pricing.matrix.title') }}</h2>
                @unless ($contentsConfirmed)
                    <x-placeholder class="text-sm text-muted">{{ __('pricing.matrix.note') }}</x-placeholder>
                @endunless
            </div>

            {{-- Scrolls sideways on phones, with the feature names pinned. --}}
            <div class="mt-8 overflow-x-auto border border-line">
                <table class="w-full min-w-[640px] border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-line bg-surface">
                            <th scope="col" class="sticky left-0 bg-surface px-4 py-4 font-semibold">{{ __('pricing.matrix.feature') }}</th>
                            @foreach ($plans as $key => $plan)
                                <th scope="col" class="px-4 py-4 text-center font-semibold">
                                    {{ __("plans.{$key}.name") }}
                                    @if ($plan['popular'])
                                        <span class="mt-1 block text-xs font-medium text-coral">{{ __('plans.popular') }}</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach (config('marketing.plan_matrix') as $group => $rows)
                        <tbody>
                            <tr class="bg-canvas">
                                <th scope="colgroup" colspan="{{ count($plans) + 1 }}" class="sticky left-0 bg-canvas px-4 pb-2 pt-6 text-xs font-semibold uppercase tracking-widest text-coral">
                                    {{ __("pricing.matrix.groups.{$group}") }}
                                </th>
                            </tr>
                            @foreach ($rows as $row => $values)
                                <tr class="border-t border-line">
                                    <th scope="row" class="sticky left-0 bg-canvas px-4 py-3 font-normal text-muted">{{ __("pricing.matrix.rows.{$row}") }}</th>
                                    @foreach ($values as $value)
                                        <td class="px-4 py-3 text-center">
                                            @if ($value === true)
                                                <x-icon name="check" class="mx-auto size-5 text-success" />
                                                <span class="sr-only">{{ __('pricing.matrix.values.included') }}</span>
                                            @elseif ($value === false)
                                                <span class="text-subtle" aria-hidden="true">—</span>
                                                <span class="sr-only">{{ __('pricing.matrix.values.not_included') }}</span>
                                            @else
                                                <span class="font-medium">{{ __("pricing.matrix.values.{$value}") }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
    </section>

    {{-- Add-ons --}}
    <section class="border-t border-line bg-surface py-16 lg:py-24">
        <div class="container-page">
            <x-section-heading :title="__('pricing.addons.title')" :lead="__('pricing.addons.lead')" />

            <ul class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (config('marketing.addons') as $key => $addon)
                    <li>
                        <x-card class="flex h-full flex-col bg-canvas">
                            <x-feature-icon :icon="$addon['icon']" />
                            <h3 class="mt-4 font-semibold">{{ __("pricing.addons.items.{$key}.name") }}</h3>
                            <p class="mt-2 flex-1 text-sm text-muted">{{ __("pricing.addons.items.{$key}.body") }}</p>
                            <p class="mt-5 flex flex-wrap items-baseline gap-x-1.5">
                                <span class="tabular text-xl font-bold">
                                    <x-placeholder :confirmed="config('marketing.prices_confirmed')">{{ Price::shillings($addon['cents']) }}</x-placeholder>
                                </span>
                                <span class="text-sm text-muted">{{ __("pricing.addons.per.{$addon['per']}") }}</span>
                            </p>
                        </x-card>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- FAQ: native disclosure widgets, no JavaScript --}}
    <section class="py-16 lg:py-24">
        <div class="container-page max-w-3xl">
            <h2 class="text-center text-headline font-bold lg:text-headline-lg">{{ __('pricing.faq.title') }}</h2>

            <div class="mt-10 divide-y divide-line border-y border-line">
                @foreach (__('pricing.faq.items') as $item)
                    <details class="group/faq">
                        <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-4 font-semibold transition-colors hover:text-coral [&::-webkit-details-marker]:hidden">
                            {{ $item['q'] }}
                            <x-icon name="chevron-down" class="size-5 text-muted transition-transform group-open/faq:rotate-180" />
                        </summary>
                        <p class="pb-5 text-muted">{{ $item['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <x-closing-cta :title="__('pricing.cta.title')" :lead="__('pricing.cta.lead')" :button="__('pricing.cta.button')" photo="cta/owner" :photo-alt="__('home.cta.photo_alt')" />

</x-layouts.site>
