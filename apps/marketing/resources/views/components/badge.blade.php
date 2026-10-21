@props([
    'variant' => 'neutral',
    'dot' => false,
])

@php
    $variants = [
        'neutral' => 'border-line bg-white/5 text-muted',
        'coral' => 'border-coral/25 bg-coral/10 text-coral',
        'success' => 'border-success/25 bg-success/10 text-success',
        'mpesa' => 'border-mpesa/25 bg-mpesa/10 text-mpesa',
        'pending' => 'border-pending/25 bg-pending/10 text-pending',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold tracking-wide',
    $variants[$variant],
]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
