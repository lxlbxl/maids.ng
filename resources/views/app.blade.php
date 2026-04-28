<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="Maids.ng — Nigeria's most trusted platform for finding verified domestic helpers.">

        <title inertia>{{ config('app.name', 'Maids.ng') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&family=DM+Sans:wght@300;400;500;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

        <!-- Favicon -->
        <link rel="icon" href="/favicon.png" type="image/png">

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        <script src="https://js.paystack.co/v1/inline.js"></script>
        <script src="https://checkout.flutterwave.com/v3.js"></script>
        @inertiaHead
    </head>
    <body class="font-body antialiased bg-ivory text-espresso">
        @inertia
    </body>
</html>
