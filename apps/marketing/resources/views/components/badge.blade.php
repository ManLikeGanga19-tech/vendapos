@props([
    'variant' => 'neutral',
    'dot' => false,
])

@php
    $variants = [
        'neutral' => 'bg-white/5 text-muted',
        'coral' => 'bg-coral/10 text-coral',
        'success' => 'bg-success/10 text-success',
        'mpesa' => 'bg-mpesa/10 text-mpesa',
        'pending' => 'bg-pending/10 text-pending',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold tracking-wide',
    $variants[$variant],
]) }}>
    @if ($dot)
        <span class="size-1.5 bg-current" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
