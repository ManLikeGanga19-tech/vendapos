@props(['alternates'])

<footer class="border-t border-line bg-surface">
    <div class="container-page grid gap-10 py-12 md:grid-cols-[2fr_1fr_1fr]">
        <div class="max-w-sm">
            <x-site.logo />
            <p class="mt-4 text-sm text-muted">{{ __('footer.tagline') }}</p>
            <x-site.language-switcher :alternates="$alternates" direction="up" align="left" class="mt-6 inline-block" />
        </div>

        <div>
            <h2 class="text-sm font-semibold text-ink">{{ __('footer.solutions') }}</h2>
            <ul class="mt-4 space-y-3 text-sm text-muted">
                @foreach (__('footer.verticals') as $vertical)
                    <li>{{ $vertical }}</li>
                @endforeach
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-ink">{{ __('footer.contact') }}</h2>
            <ul class="mt-4 space-y-3 text-sm text-muted">
                <li class="flex items-center gap-2">
                    <x-icon name="mail" class="size-4" />
                    <x-placeholder :confirmed="config('marketing.contact.confirmed')">{{ config('marketing.contact.email') }}</x-placeholder>
                </li>
                <li class="flex items-center gap-2">
                    <x-icon name="phone" class="size-4" />
                    <x-placeholder :confirmed="config('marketing.contact.confirmed')">{{ config('marketing.contact.phone') }}</x-placeholder>
                </li>
                <li class="flex items-center gap-2">
                    <x-icon name="map-pin" class="size-4" />
                    {{ __('footer.location') }}
                </li>
            </ul>
        </div>
    </div>

    <div class="border-t border-line">
        <div class="container-page flex flex-col gap-2 py-6 text-xs text-subtle sm:flex-row sm:justify-between">
            <p>&copy; {{ now('Africa/Nairobi')->year }} DanamoTech Ltd. {{ __('footer.rights') }} {{ __('footer.built_by') }}</p>
            <p>{{ __('footer.billing') }}</p>
        </div>
    </div>
</footer>
