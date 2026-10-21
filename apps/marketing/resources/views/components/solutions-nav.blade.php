@props(['current'])

@php
    $icons = [
        'restaurants' => 'utensils', 'bars' => 'beer', 'salons' => 'scissors', 'shops' => 'store',
        'supermarkets' => 'shopping-cart', 'hardware' => 'hammer', 'pharmacy' => 'pill', 'liquor' => 'package',
    ];
@endphp

{{-- Cross-links between solution pages; labels come from the home page's vertical titles. --}}
<section class="border-t border-line py-14" aria-labelledby="other-solutions">
    <div class="container-page">
        <h2 id="other-solutions" class="text-center text-xl font-semibold">{{ __('solutions.common.other') }}</h2>
        <ul class="reveal-children mt-8 grid grid-cols-2 gap-px border border-line bg-line sm:grid-cols-4">
            @foreach ($icons as $key => $icon)
                @continue($key === $current || ! Route::has("solutions.{$key}"))
                <li>
                    <a href="{{ route("solutions.{$key}") }}" class="flex h-full items-center gap-3 bg-canvas p-4 text-sm font-medium text-muted transition-colors hover:bg-surface hover:text-ink">
                        <x-icon :name="$icon" class="size-5 text-coral" />
                        {{ __("home.verticals.items.{$key}.title") }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</section>
