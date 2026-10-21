@props([
    'name',
    'alt',
    'sizes' => '100vw',
    'ratio' => '4/3',
    'icon' => 'store',
    'eager' => false,
])

@php
    $image = app(\App\Support\ImageManifest::class)->get($name);
    $srcset = fn (string $format) => collect($image['widths'] ?? [])
        ->map(fn (int $w) => asset("images/{$name}-{$w}.{$format}")." {$w}w")
        ->implode(', ');
@endphp

@if ($image)
    <picture>
        @foreach ($image['formats'] as $format)
            <source type="image/{{ $format }}" srcset="{{ $srcset($format) }}" sizes="{{ $sizes }}">
        @endforeach
        <img src="{{ asset("images/{$name}-".last($image['widths']).'.webp') }}"
             alt="{{ $alt }}"
             width="{{ $image['width'] }}"
             height="{{ $image['height'] }}"
             loading="{{ $eager ? 'eager' : 'lazy' }}"
             decoding="async"
             @if ($eager) fetchpriority="high" @endif
             {{ $attributes->merge(['class' => 'h-auto w-full object-cover']) }}
             style="aspect-ratio: {{ $ratio }}">
    </picture>
@else
    {{-- Photo not supplied yet: a branded tile that keeps the layout and the alt text. --}}
    <div role="img" aria-label="{{ $alt }}" data-image-placeholder="{{ $name }}"
         {{ $attributes->merge(['class' => 'flex w-full items-center justify-center overflow-hidden bg-elevated']) }}
         style="aspect-ratio: {{ $ratio }}">
        <x-icon :name="$icon" class="size-12 text-coral/60" />
    </div>
@endif
