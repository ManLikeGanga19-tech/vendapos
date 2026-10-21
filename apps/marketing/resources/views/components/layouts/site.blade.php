@props([
    'title',
    'description',
])

@php
    $alternates = \App\Support\LocalizedUrls::forRequest(request());
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f0a1e">

    <title>{{ $title }} · VendaPOS</title>
    <meta name="description" content="{{ $description }}">

    <link rel="canonical" href="{{ $alternates[app()->getLocale()] ?? url()->current() }}">
    @foreach ($alternates as $locale => $url)
        <link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('root') }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="VendaPOS">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'sw' ? 'sw_KE' : 'en_KE' }}">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @fonts
    @vite('resources/css/app.css')

    {{--
        Outdated-browser notice. Plain CSS outside Tailwind's layers so it still renders
        where Tailwind 4 doesn't; any browser supporting color-mix() (Chrome 111+,
        Safari 16.2+, Firefox 113+) hides it. Unknown @supports tests count as false.
    --}}
    <style>
        .browser-notice { display: block; margin: 0; padding: 12px 16px; background: #f59e0b; color: #0f0a1e; font: 600 15px/1.4 system-ui, sans-serif; text-align: center; }
        .browser-notice a { color: #0f0a1e; text-decoration: underline; }
        @supports (color: color-mix(in srgb, red, blue)) { .browser-notice { display: none; } }
    </style>
</head>
<body class="min-h-dvh">
    <p class="browser-notice" role="alert">
        {{ __('common.outdated_browser.message') }}
        <a href="https://play.google.com/store/apps/details?id=com.android.chrome" rel="noopener">{{ __('common.outdated_browser.action') }}</a>
    </p>

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-control focus:bg-coral focus:px-4 focus:py-2 focus:text-canvas">
        {{ __('common.skip_to_content') }}
    </a>

    <x-site.header :alternates="$alternates" />

    <main id="main">
        {{ $slot }}
    </main>

    <x-site.footer :alternates="$alternates" />
</body>
</html>
