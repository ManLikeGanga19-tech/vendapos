@props([
    'key',
    'headingLevel' => 'h3',
])

@php
    $icons = [
        'restaurants' => 'utensils', 'bars' => 'beer', 'salons' => 'scissors', 'shops' => 'store',
        'supermarkets' => 'shopping-cart', 'hardware' => 'hammer', 'pharmacy' => 'pill', 'liquor' => 'package',
    ];
    $item = __("home.verticals.items.{$key}");
@endphp

{{-- A business type as one big link: photo, title, summary and a visible "Learn more" button. --}}
<a href="{{ route("solutions.{$key}") }}" {{ $attributes->class([
    'group reveal flex h-full flex-col border border-line bg-canvas transition-colors hover:border-line-strong',
]) }}>
    <div class="overflow-hidden">
        <x-picture :name="'verticals/'.$key" :alt="$item['alt']" :icon="$icons[$key]"
                   class="transition-transform duration-300 group-hover:scale-[1.03]"
                   sizes="(min-width: 1024px) 280px, (min-width: 640px) 50vw, 100vw" />
    </div>
    <div class="flex flex-1 flex-col p-5">
        <{{ $headingLevel }} class="flex items-center gap-2 font-semibold">
            <x-icon :name="$icons[$key]" class="size-5 text-coral" />{{ $item['title'] }}
        </{{ $headingLevel }}>
        <p class="mt-2 flex-1 text-sm text-muted">{{ $item['body'] }}</p>
        <span class="btn mt-5 min-h-10 self-start border border-line px-4 text-sm text-ink group-hover:border-coral group-hover:text-coral">
            {{ __('common.learn_more') }}
            <x-icon name="arrow-right" class="btn-icon size-4" />
        </span>
    </div>
</a>
