<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description"
        content="{{ $page['props']['meta']['description'] ?? 'Find verified housekeepers, nannies, cooks and drivers in Nigeria. NIN-verified staff. 10-day money-back guarantee.' }}">
    <link rel="canonical" href="{{ $page['props']['meta']['canonical'] ?? url()->current() }}">
    <meta property="og:title" content="{{ $page['props']['meta']['title'] ?? 'Maids.ng — Nigeria\'s Domestic Staff Platform' }}">
    <meta property="og:description" content="{{ $page['props']['meta']['description'] ?? 'Find verified domestic staff in Nigeria.' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-default.png') }}">
    <meta property="og:site_name" content="Maids.ng">

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "@id": "https://maids.ng/#organization",
      "name": "Maids.ng",
      "alternateName": ["Maids Nigeria", "MaidsNG"],
      "url": "https://maids.ng",
      "description": "Maids.ng is Nigeria's leading AI-powered domestic staff matching platform. We connect Nigerian families with NIN-verified housekeepers, nannies, cooks, drivers, and elderly carers. Our algorithm matches employers with verified domestic staff in Lagos, Abuja, and Port Harcourt. One-time matching fee of ₦5,000 with a 10-day money-back guarantee.",
      "foundingDate": "2024",
      "foundingLocation": "Lagos, Nigeria",
      "areaServed": "Nigeria",
      "knowsAbout": [
        "Domestic Staff Matching",
        "NIN Verification Nigeria",
        "Housekeeper Hiring Lagos",
        "Nanny Services Abuja",
        "Domestic Worker Salary Nigeria"
      ]
    }
    </script>

    <title inertia>{{ config('app.name', 'Maids.ng') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&family=DM+Sans:wght@300;400;500;700&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" href="/favicon.png" type="image/png">

    <!-- Scripts -->
    @routes
    @viteReactRefresh
    @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script src="https://checkout.flutterwave.com/v3.js" crossorigin="anonymous"></script>
    @inertiaHead
</head>

<body class="font-body antialiased bg-ivory text-espresso">
    @inertia
</body>

</html>