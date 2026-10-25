@props([
    'eyebrow' => null,
    'title',
    'lead' => null,
    'align' => 'center',
])

<div {{ $attributes->class(['reveal max-w-3xl', 'mx-auto text-center' => $align === 'center']) }}>
    @if ($eyebrow)
        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-coral">{{ $eyebrow }}</p>
    @endif
    <h2 class="text-headline font-bold text-balance lg:text-headline-lg">{{ $title }}</h2>
    @if ($lead)
        <p class="mt-4 text-lg text-muted text-pretty">{{ $lead }}</p>
    @endif
</div>
