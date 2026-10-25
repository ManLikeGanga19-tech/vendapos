@props([
    'icon',
    'brand' => null,
    'size' => 'md',
])

{{-- Leading visual for a feature: the partner's logo where one applies (M-Pesa, KRA), otherwise a coral icon tile. --}}
@if ($brand)
    <x-brand-mark :name="$brand" :icon="$icon" :size="$size" {{ $attributes }} />
@else
    <span {{ $attributes->class([
        'flex shrink-0 items-center justify-center bg-coral/10 text-coral',
        'size-12' => $size === 'lg',
        'size-9' => $size === 'md',
    ]) }}>
        <x-icon :name="$icon" @class(['size-6' => $size === 'lg', 'size-5' => $size === 'md']) />
    </span>
@endif
