@props([
    'name',
    'icon',
    'size' => 'sm',
])

@php
    // Official partner artwork in public/brand (see README there); first match wins.
    $file = collect(['svg', 'png'])
        ->map(fn (string $ext) => "brand/{$name}.{$ext}")
        ->first(fn (string $path) => is_file(public_path($path)));

    [$chip, $mark] = match ($size) {
        'lg' => ['size-12 rounded-control p-1.5', 'h-full'],
        'md' => ['size-9 rounded-control p-1', 'h-full'],
        default => ['size-5 rounded p-0.5', 'h-full'],
    };
@endphp

{{-- Partner marks sit on a small white chip so they read correctly on the dark theme. --}}
@if ($file)
    <span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center bg-white', $chip]) }}>
        <img src="{{ asset($file) }}" alt="" class="{{ $mark }} w-auto object-contain">
    </span>
@else
    <x-icon :name="$icon" {{ $attributes->merge(['class' => 'size-3.5']) }} />
@endif
