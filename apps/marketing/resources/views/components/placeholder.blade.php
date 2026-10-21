@props(['confirmed' => false])

{{--
    Marks a figure or claim we have not verified yet. Rendered visibly on purpose,
    so an unconfirmed number can never pass as a real one on a live page.
--}}
@if ($confirmed)
    {{ $slot }}
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-baseline gap-2 rounded-control border border-dashed border-pending/60 px-2']) }}>
        {{ $slot }}
        <span class="text-[10px] font-semibold uppercase tracking-widest text-pending">{{ __('common.to_be_confirmed') }}</span>
    </span>
@endif
