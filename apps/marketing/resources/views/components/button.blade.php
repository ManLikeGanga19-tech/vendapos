@props([
    'href',
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
])

@php
    $variants = [
        'primary' => 'bg-coral text-canvas hover:bg-coral-hover',
        'secondary' => 'border border-line text-muted hover:border-line-strong hover:bg-white/5 hover:text-ink',
        'mpesa' => 'bg-mpesa text-canvas hover:brightness-110',
    ];

    $sizes = [
        'md' => 'min-h-11 px-4 text-base',
        'lg' => 'min-h-12 px-6 text-base',
    ];
@endphp

<a href="{{ $href }}" {{ $attributes->class(['btn', $variants[$variant], $sizes[$size]]) }}>
    {{ $slot }}
    @if ($icon)
        <x-icon :name="$icon" class="btn-icon size-4" />
    @endif
</a>
