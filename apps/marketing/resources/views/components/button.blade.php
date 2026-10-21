@props([
    'href',
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
])

@php
    $variants = [
        'primary' => 'bg-coral text-canvas shadow-glow hover:bg-coral-hover',
        'secondary' => 'border border-line text-muted hover:bg-white/5 hover:text-ink',
        'mpesa' => 'bg-mpesa text-canvas hover:brightness-110',
        'paper' => 'bg-canvas text-ink hover:bg-elevated',
    ];

    $sizes = [
        'md' => 'min-h-11 px-4 text-base',
        'lg' => 'min-h-12 px-6 text-base',
    ];
@endphp

<a href="{{ $href }}" {{ $attributes->class([
    'inline-flex items-center justify-center gap-2 rounded-card font-semibold transition active:scale-[0.98]',
    $variants[$variant],
    $sizes[$size],
]) }}>
    {{ $slot }}
    @if ($icon)
        <x-icon :name="$icon" class="size-4" />
    @endif
</a>
