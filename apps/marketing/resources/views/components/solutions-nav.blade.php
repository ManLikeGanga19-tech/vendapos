@props(['current'])

@php
    $others = array_values(array_filter(
        array_keys(__('home.verticals.items')),
        fn (string $key) => $key !== $current && Route::has("solutions.{$key}"),
    ));
@endphp

{{-- End-of-page cross-links: every other business type as a clickable card. --}}
<section class="border-t border-line bg-surface py-16 lg:py-20" aria-labelledby="other-solutions">
    <div class="container-page">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 id="other-solutions" class="text-headline font-bold">{{ __('solutions.common.other') }}</h2>
            <x-button :href="route('solutions')" variant="secondary" icon="arrow-right">{{ __('home.verticals.all') }}</x-button>
        </div>
        <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($others as $key)
                <li @class(['hidden lg:block' => $loop->index >= 4])><x-solution-card :key="$key" /></li>
            @endforeach
        </ul>
    </div>
</section>
