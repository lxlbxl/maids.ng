<!DOCTYPE html>
<html lang="en-NG" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $page->meta_title ?? 'Maids.ng' }}</title>
    <meta name="description" content="{{ $page->meta_description ?? 'Nigeria\'s leading AI-powered domestic staff matching platform.' }}">
    <link rel="canonical" href="{{ $page->canonical_url ?? url($page->url_path ?? '/') }}">

    <meta property="og:title" content="{{ $page->meta_title ?? 'Maids.ng' }}">
    <meta property="og:description" content="{{ $page->meta_description ?? 'Find verified domestic staff in Nigeria.' }}">
    <meta property="og:url" content="{{ url($page->url_path ?? '/') }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('maids-logo.png') }}">
    <meta property="og:site_name" content="Maids.ng">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $page->meta_title ?? 'Maids.ng' }}">
    <meta name="twitter:description" content="{{ $page->meta_description ?? 'Find verified domestic staff in Nigeria.' }}">

    @if(!empty($page->schema_markup))
    @foreach($page->schema_markup as $schema)
    <script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
    @endforeach
    @endif

    <link rel="stylesheet" href="{{ asset('css/seo.css') }}">
    <link rel="preconnect" href="{{ config('app.url') }}">
</head>
<body>
    @include('seo.partials.header')

    <main>
        @yield('content')
    </main>

    @include('seo.partials.breadcrumbs')

    @include('seo.partials.footer')

    <script defer>
    </script>
</body>
</html>
