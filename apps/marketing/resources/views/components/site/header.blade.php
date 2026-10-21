@props(['alternates'])

@php
    // route => [label, pattern that marks it active]. Solutions points at the first
    // solution page until there are enough of them for a dropdown.
    $links = collect([
        'product' => ['nav.product', 'product'],
        'solutions.restaurants' => ['nav.solutions', 'solutions.*'],
        'pricing' => ['nav.pricing', 'pricing'],
        'compliance' => ['nav.compliance', 'compliance'],
    ])->filter(fn (array $link, string $route) => Route::has($route));
@endphp

<header class="sticky top-0 z-50 border-b border-line bg-canvas/95">
    <div class="container-page flex h-16 items-center justify-between gap-4 lg:h-20">
        <a href="{{ route('home') }}" aria-label="{{ __('nav.home') }}">
            <x-site.logo />
        </a>

        @if ($links->isNotEmpty())
            <nav class="hidden lg:block" aria-label="{{ __('nav.primary') }}">
                <ul class="flex items-center gap-8">
                    @foreach ($links as $route => [$label, $active])
                        <li>
                            <a href="{{ route($route) }}"
                               @if (request()->routeIs($active)) aria-current="page" @endif
                               @class([
                                   'py-2 transition',
                                   'border-b-2 border-coral text-ink' => request()->routeIs($active),
                                   'text-muted hover:text-ink' => ! request()->routeIs($active),
                               ])>{{ __($label) }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="flex items-center gap-3">
            <x-site.language-switcher :alternates="$alternates" class="hidden sm:block" />

            @if (Route::has('demo'))
                <x-button :href="route('demo')" class="hidden sm:inline-flex">{{ __('nav.book_demo') }}</x-button>
            @endif

            {{-- <details> gives a working menu with zero JavaScript. --}}
            <details class="group lg:hidden" data-dismissible>
                <summary class="btn size-11 cursor-pointer list-none border border-line text-ink hover:border-line-strong hover:bg-white/5 [&::-webkit-details-marker]:hidden">
                    <x-icon name="menu" class="size-5 group-open:hidden" />
                    <x-icon name="x" class="hidden size-5 group-open:block" />
                    <span class="sr-only">{{ __('nav.menu') }}</span>
                </summary>

                <div class="absolute inset-x-0 top-full border-b border-line bg-canvas shadow-float">
                    <nav class="container-page py-4" aria-label="{{ __('nav.primary') }}">
                        <ul class="divide-y divide-line">
                            @foreach ($links as $route => [$label, $active])
                                <li>
                                    <a href="{{ route($route) }}" class="flex min-h-12 items-center text-lg text-ink">{{ __($label) }}</a>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                            <x-site.language-switcher :alternates="$alternates" direction="up" align="left" />
                            @if (Route::has('demo'))
                                <x-button :href="route('demo')">{{ __('nav.book_demo') }}</x-button>
                            @endif
                        </div>
                    </nav>
                </div>
            </details>
        </div>
    </div>
</header>
