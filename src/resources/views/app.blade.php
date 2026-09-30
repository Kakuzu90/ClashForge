@php($meta = $meta ?? \App\Support\Seo\PageMeta::defaults())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        {{-- Mirrors --clr-navy-900 / --bg-page in resources/css/app.css (specs/18 §3); keep in sync. --}}
        <meta name="theme-color" content="#0e1220">
        <title inertia>{{ $meta->fullTitle() }}</title>
        {{-- Server-rendered meta: present even when SSR is down (specs/06 §2). --}}
        <meta name="description" content="{{ $meta->resolvedDescription() }}">
        <link rel="canonical" href="{{ $meta->resolvedCanonical() }}">
        @if ($meta->noindex)
            <meta name="robots" content="noindex, nofollow">
        @endif
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:type" content="{{ $meta->type }}">
        <meta property="og:title" content="{{ $meta->fullTitle() }}">
        <meta property="og:description" content="{{ $meta->resolvedDescription() }}">
        <meta property="og:url" content="{{ $meta->resolvedCanonical() }}">
        @if ($meta->resolvedImage())
            <meta property="og:image" content="{{ $meta->resolvedImage() }}">
            <meta name="twitter:card" content="summary_large_image">
        @endif
        @if ($meta->jsonLd)
            {{-- @json escapes <, >, &, ' and " so user text cannot break out of the script tag. --}}
            <script type="application/ld+json">@json($meta->jsonLd)</script>
        @endif
        <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('node_modules/@fontsource/lilita-one/files/lilita-one-latin-400-normal.woff2') }}">
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
