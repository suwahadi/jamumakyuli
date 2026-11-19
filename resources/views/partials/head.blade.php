@php
    use Illuminate\Support\Str;

    $appName = config('app.name', 'Jamu Mak Yuli');
    $siteName = setting('site_name', $appName);
    $tagline = setting('site_tagline', 'Semangat menjemput buah hati ❤️');

    $pageTitle = $seoTitle ?? ($title ?? null);
    $metaTitle = filled($pageTitle)
        ? Str::of($pageTitle)->stripTags()->squish().' | '.$siteName
        : $siteName.' | '.$tagline;

    $defaultDescription = setting(
        'site_description',
        'Jamu Mak Yuli. Ramuan rempah kering untuk ikhtiar program hamil pasutri, direbus sendiri di rumah. Kirim ke seluruh Indonesia.',
    );
    $metaDescription = (string) Str::of($seoDescription ?? $defaultDescription)->stripTags()->squish()->limit(160);

    $metaKeywords = $seoKeywords ?? setting(
        'site_keywords',
        'jamu promil, jamu program hamil, ramuan rempah kering, jamu pasutri, jamu tradisional, Jamu Mak Yuli',
    );

    $ogImage = $seoImage ?? asset('assets/images/og-image.jpg');
    $ogType = $seoType ?? 'website';
    $canonical = url()->current();
    $locale = 'id_ID';

    $indexable = request()->routeIs('home', 'products.index', 'products.show', 'pages.show', 'kontak');
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>{{ $metaTitle }}</title>
<meta name="description" content="{{ $metaDescription }}" />
<meta name="keywords" content="{{ $metaKeywords }}" />
<meta name="author" content="{{ $siteName }}" />
<meta name="robots" content="{{ $indexable ? 'index, follow, max-image-preview:large' : 'noindex, nofollow' }}" />
<link rel="canonical" href="{{ $canonical }}" />
<meta name="theme-color" content="#2F7D32" />

<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
<link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}" />
<link rel="manifest" href="{{ asset('site.webmanifest') }}" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" />

<meta property="og:site_name" content="{{ $siteName }}" />
<meta property="og:type" content="{{ $ogType }}" />
<meta property="og:title" content="{{ $metaTitle }}" />
<meta property="og:description" content="{{ $metaDescription }}" />
<meta property="og:url" content="{{ $canonical }}" />
<meta property="og:image" content="{{ $ogImage }}" />
<meta property="og:locale" content="{{ $locale }}" />

<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $metaTitle }}" />
<meta name="twitter:description" content="{{ $metaDescription }}" />
<meta name="twitter:image" content="{{ $ogImage }}" />

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])

@if ($appearance ?? true)
    @fluxAppearance
@endif
