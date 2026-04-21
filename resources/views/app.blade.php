<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $seo = $page['props']['seo'] ?? [];
        $seoTitle = $seo['title'] ?? null;
        $seoDescription = $seo['description'] ?? 'Real-time conflict monitoring and OSINT platform. Aggregating, verifying, and mapping global conflict events.';
        $seoCanonical = $seo['canonical'] ?? null;
        $seoOgTitle = $seo['ogTitle'] ?? $seoTitle ?? config('app.name', 'ClashMonitor');
        $seoOgDescription = $seo['ogDescription'] ?? $seoDescription;
        $seoOgImage = $seo['ogImage'] ?? url('/images/og-banner.jpg');
        $seoOgType = $seo['ogType'] ?? 'website';
        $seoOgLocale = $seo['ogLocale'] ?? 'en_US';
        $seoTwitterCard = $seo['twitterCard'] ?? 'summary_large_image';
        $seoRobots = $seo['robots'] ?? null;
        $seoPublishedAt = $seo['publishedAt'] ?? null;
        $seoModifiedAt = $seo['modifiedAt'] ?? null;
        $seoPrevUrl = $seo['prevUrl'] ?? null;
        $seoNextUrl = $seo['nextUrl'] ?? null;
        $seoAlternates = $seo['alternateLocales'] ?? null;
        $jsonLd = $seo['jsonLd'] ?? null;
        $breadcrumbs = $seo['breadcrumbs'] ?? null;
        $fullTitle = $seoTitle ? ($seoTitle . ' — ClashMonitor') : 'ClashMonitor';

        // Build breadcrumb JSON-LD in PHP to avoid Blade @context directive issue
        $breadcrumbJsonLd = null;
        if ($breadcrumbs) {
            $breadcrumbSchema = new stdClass();
            $breadcrumbSchema->{'@context'} = 'https://schema.org';
            $breadcrumbSchema->{'@type'} = 'BreadcrumbList';
            $breadcrumbSchema->itemListElement = collect($breadcrumbs)->map(function($crumb, $i) {
                $item = new stdClass();
                $item->{'@type'} = 'ListItem';
                $item->position = $i + 1;
                $item->name = $crumb['name'];
                if (isset($crumb['url'])) {
                    $item->item = $crumb['url'];
                }
                return $item;
            })->values();
            $breadcrumbJsonLd = json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    @endphp
    <title inertia>{{ $fullTitle }}</title>
    <link rel="icon" type="image/svg+xml" href="/icon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#3D7A32">

    {{-- SEO Meta Tags (server-rendered for crawlers) --}}
    <meta name="description" content="{{ $seoDescription }}">
    @if($seoCanonical)
        <link rel="canonical" href="{{ $seoCanonical }}">
    @endif
    @if($seoRobots)
        <meta name="robots" content="{{ $seoRobots }}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:type" content="{{ $seoOgType }}">
    <meta property="og:site_name" content="ClashMonitor">
    <meta property="og:title" content="{{ $seoOgTitle }}">
    <meta property="og:description" content="{{ $seoOgDescription }}">
    <meta property="og:image" content="{{ $seoOgImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="{{ $seoOgLocale }}">
    @if($seoCanonical)
        <meta property="og:url" content="{{ $seoCanonical }}">
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="{{ $seoTwitterCard }}">
    <meta name="twitter:title" content="{{ $seoOgTitle }}">
    <meta name="twitter:description" content="{{ $seoOgDescription }}">
    <meta name="twitter:image" content="{{ $seoOgImage }}">

    {{-- Article metadata --}}
    @if($seoPublishedAt)
        <meta property="article:published_time" content="{{ $seoPublishedAt }}">
    @endif
    @if($seoModifiedAt)
        <meta property="article:modified_time" content="{{ $seoModifiedAt }}">
    @endif

    {{-- hreflang alternates --}}
    @if($seoAlternates)
        @foreach($seoAlternates as $alt)
            <link rel="alternate" hreflang="{{ $alt['locale'] }}" href="{{ $alt['url'] }}">
        @endforeach
    @endif

    {{-- Pagination --}}
    @if($seoPrevUrl)
        <link rel="prev" href="{{ $seoPrevUrl }}">
    @endif
    @if($seoNextUrl)
        <link rel="next" href="{{ $seoNextUrl }}">
    @endif

    {{-- Feed autodiscovery --}}
    <link rel="alternate" type="application/atom+xml" title="ClashMonitor Events" href="{{ url('/feed/events') }}">
    <link rel="alternate" type="application/atom+xml" title="ClashMonitor Briefings" href="{{ url('/feed/briefings') }}">

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead

    {{-- JSON-LD Structured Data --}}
    @if($jsonLd)
        @foreach($jsonLd as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endforeach
    @endif
    @if($breadcrumbJsonLd)
        <script type="application/ld+json">{!! $breadcrumbJsonLd !!}</script>
    @endif
</head>
<body class="bg-black text-text-primary font-sans antialiased">
    @inertia
</body>
</html>
