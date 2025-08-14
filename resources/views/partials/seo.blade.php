@php
    $title = $title ?? ($meta['title'] ?? config('app.name'));
    $description = $description ?? ($meta['description'] ?? 'DigitalCity CMS');
    $canonical = $canonical ?? url()->current();
    $image = $image ?? ($meta['image'] ?? asset('favicon.ico'));
@endphp

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}"/>

<link rel="canonical" href="{{ $canonical }}"/>

<!-- OpenGraph -->
<meta property="og:title" content="{{ $title }}"/>
<meta property="og:description" content="{{ $description }}"/>
<meta property="og:type" content="website"/>
<meta property="og:url" content="{{ $canonical }}"/>
<meta property="og:image" content="{{ $image }}"/>

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image"/>
<meta name="twitter:title" content="{{ $title }}"/>
<meta name="twitter:description" content="{{ $description }}"/>
<meta name="twitter:image" content="{{ $image }}"/>

<!-- Basic favicons (optional) -->
<link rel="icon" href="/favicon.ico"/>

