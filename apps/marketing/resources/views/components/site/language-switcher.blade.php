@props(['alternates'])

<nav {{ $attributes }} aria-label="{{ __('common.language') }}">
    <ul class="flex items-center gap-1 rounded-full border border-line p-1 text-xs font-semibold">
        @foreach (config('marketing.locales') as $code => $label)
            @php($current = $code === app()->getLocale())
            <li>
                <a href="{{ $alternates[$code] ?? route('home', ['locale' => $code]) }}"
                   hreflang="{{ $code }}"
                   lang="{{ $code }}"
                   @if ($current) aria-current="true" @endif
                   @class([
                       'flex h-9 items-center rounded-full px-3 transition',
                       'bg-elevated text-ink' => $current,
                       'text-muted hover:text-ink' => ! $current,
                   ])>
                    <span aria-hidden="true">{{ strtoupper($code) }}</span>
                    <span class="sr-only">{{ $label }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
