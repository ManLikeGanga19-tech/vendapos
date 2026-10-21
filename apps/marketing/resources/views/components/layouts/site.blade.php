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
</head>
<body class="min-h-dvh">
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
