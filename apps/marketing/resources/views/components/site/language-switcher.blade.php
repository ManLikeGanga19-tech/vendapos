@props([
    'alternates',
    'direction' => 'down',
    'align' => 'right',
])

@php
    $locales = config('marketing.locales');
    $current = app()->getLocale();
@endphp

{{-- Native <details> dropdown: works without JavaScript; the layout's tiny script adds outside-click and Escape to close. --}}
<details {{ $attributes->class(['group/lang relative']) }} data-dismissible>
    <summary class="btn h-10 cursor-pointer list-none gap-2.5 border border-line px-3 text-sm font-medium text-ink hover:border-line-strong hover:bg-white/5 [&::-webkit-details-marker]:hidden"
             aria-label="{{ __('common.language') }}: {{ $locales[$current]['label'] }}">
        <img src="{{ asset('flags/'.$locales[$current]['flag'].'.svg') }}" alt="" width="20" height="15" class="h-[15px] w-5 object-cover ring-1 ring-white/15">
        <span>{{ $locales[$current]['label'] }}</span>
        <x-icon name="chevron-down" class="size-4 text-muted transition-transform group-open/lang:rotate-180" />
    </summary>

    <ul @class([
        'absolute z-50 min-w-48 border border-line bg-elevated p-1 shadow-float',
        'top-full mt-2' => $direction === 'down',
        'bottom-full mb-2' => $direction === 'up',
        'right-0' => $align === 'right',
        'left-0' => $align === 'left',
    ])>
        @foreach ($locales as $code => $locale)
            @php($active = $code === $current)
            <li>
                <a href="{{ $alternates[$code] ?? route('home', ['locale' => $code]) }}"
                   hreflang="{{ $code }}"
                   lang="{{ $code }}"
                   @if ($active) aria-current="true" @endif
                   @class([
                       'flex min-h-11 items-center gap-3 px-3 text-sm transition-colors',
                       'text-ink' => $active,
                       'text-muted hover:bg-white/5 hover:text-ink' => ! $active,
                   ])>
                    <img src="{{ asset('flags/'.$locale['flag'].'.svg') }}" alt="" width="20" height="15" class="h-[15px] w-5 object-cover ring-1 ring-white/15">
                    <span class="flex-1">{{ $locale['label'] }}</span>
                    @if ($active)
                        <x-icon name="check" class="size-4 text-coral" />
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</details>
