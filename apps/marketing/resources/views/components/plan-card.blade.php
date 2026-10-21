@props([
    'key',
    'plan',
    'ctaUrl',
    'annual' => false,
])

@php
    use App\Support\Price;

    $confirmed = config('marketing.prices_confirmed');
    $months = config('marketing.annual_months_charged');
@endphp

{{--
    One plan. With :annual, both monthly and annual prices are rendered and the
    pricing page's billing switch shows one of them with CSS (no JavaScript).
--}}
<li @class([
    'reveal relative flex flex-col border bg-canvas p-6',
    'border-coral' => $plan['popular'],
    'border-line' => ! $plan['popular'],
])>
    @if ($plan['popular'])
        <x-badge variant="coral" class="absolute -top-3 left-6 bg-canvas">{{ __('plans.popular') }}</x-badge>
    @endif

    <h3 class="text-xl font-semibold">{{ __("plans.{$key}.name") }}</h3>
    <p class="mt-1 text-sm text-muted">{{ __("plans.{$key}.summary") }}</p>

    <div class="mt-6 min-h-16">
        @if ($plan['monthly_cents'] === null)
            <p class="text-3xl font-bold">{{ __('plans.custom') }}</p>
            <p class="mt-1 text-sm text-muted">{{ __('plans.custom_note') }}</p>
        @else
            <div @class(['group-has-[#billing-annual:checked]/billing:hidden' => $annual])>
                <p class="flex flex-wrap items-baseline gap-x-2">
                    <span class="tabular text-3xl font-bold">
                        <x-placeholder :confirmed="$confirmed">{{ Price::shillings($plan['monthly_cents']) }}</x-placeholder>
                    </span>
                    <span class="text-sm text-muted">{{ __('plans.per_month') }}</span>
                </p>
                <p class="mt-1 text-sm text-muted">{{ __('plans.billed_monthly') }}</p>
            </div>

            @if ($annual)
                <div class="hidden group-has-[#billing-annual:checked]/billing:block">
                    <p class="flex flex-wrap items-baseline gap-x-2">
                        <span class="tabular text-3xl font-bold">
                            <x-placeholder :confirmed="$confirmed">{{ Price::shillings(Price::annualMonthlyEquivalent($plan['monthly_cents'], $months)) }}</x-placeholder>
                        </span>
                        <span class="text-sm text-muted">{{ __('plans.per_month') }}</span>
                    </p>
                    <p class="mt-1 text-sm text-muted">{{ __('plans.billed_yearly', ['total' => Price::shillings(Price::annualTotal($plan['monthly_cents'], $months))]) }}</p>
                </div>
            @endif
        @endif
    </div>

    <ul class="mt-6 flex-1 space-y-3 text-sm">
        @foreach (__("plans.{$key}.features") as $feature)
            <li class="flex gap-2">
                <x-icon name="check" class="size-5 text-success" />{{ $feature }}
            </li>
        @endforeach
    </ul>

    <x-button :href="$ctaUrl" :variant="$plan['popular'] ? 'primary' : 'secondary'" class="mt-8" icon="arrow-right">
        {{ $plan['monthly_cents'] === null ? __('plans.cta_sales') : __('plans.cta') }}
    </x-button>
</li>
