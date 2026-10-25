@props([
    'id',
    'eyebrow',
    'title',
    'body',
    'points',
    'icon',
    'brand' => null,
    'reverse' => false,
])

{{-- One product feature: copy and bullet points beside a visual (the slot), alternating sides on desktop. --}}
<section id="{{ $id }}" {{ $attributes->class(['scroll-mt-24 py-14 lg:py-20']) }}>
    <div class="container-page grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
        <div @class(['reveal', 'lg:order-2' => $reverse])>
            <div class="flex items-center gap-3">
                <x-feature-icon :icon="$icon" :brand="$brand" />
                <p class="text-xs font-semibold uppercase tracking-widest text-coral">{{ $eyebrow }}</p>
            </div>
            <h2 class="mt-4 text-headline font-bold text-balance lg:text-headline-lg">{{ $title }}</h2>
            <p class="mt-4 text-lg text-muted text-pretty">{{ $body }}</p>
            <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach ($points as $point)
                    <li class="flex gap-2 text-sm">
                        <x-icon name="circle-check" class="size-5 text-success" />{{ $point }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div @class(['reveal', 'lg:order-1' => $reverse])>
            {{ $slot }}
        </div>
    </div>
</section>
