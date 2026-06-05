@extends('seo.layouts.master')

@section('content')

@php
    $page = (object)[
        'meta_title' => 'Hire Domestic Staff in Nigeria | Maids.ng',
        'meta_description' => 'Find verified housekeepers, nannies, cooks, and drivers across Nigeria. NIN-verified staff. 10-day guarantee. Start free today.',
        'url_path' => '/locations/',
        'h1' => 'Domestic Staff Across Nigeria',
        'schema_markup' => null,
    ];
@endphp

@section('content')

<section class="seo-hero">
    <div class="container">
        <h1>{{ $page->h1 }}</h1>
        <div class="answer-block">
            <p>Maids.ng connects Nigerian families with verified domestic staff across all major cities. Every helper is NIN-verified, background-checked, and matched using our AI algorithm. Whether you need a housekeeper in Lagos, a nanny in Abuja, or a driver in Port Harcourt, we have you covered.</p>
        </div>
        <div class="trust-bar">
            <span>&#10003; NIN-Verified Staff</span>
            <span>&#10003; AI-Matched</span>
            <span>&#10003; 10-Day Guarantee</span>
        </div>
        <a href="{{ url('/onboarding') }}" class="btn-primary">Start Free &rarr;</a>
    </div>
</section>

@foreach($cities->groupBy('tier') as $tier => $tierCities)
<section class="seo-section">
    <div class="container">
        <h2>Tier {{ $tier }} Cities</h2>
        <div class="hub-grid">
            @foreach($tierCities as $city)
            <div class="hub-card">
                <h3><a href="{{ url('/locations/' . $city->slug . '/') }}">{{ $city->name }}</a></h3>
                <p>{{ $city->description ?? 'Find verified domestic staff in ' . $city->name . '.' }}</p>
                <a href="{{ url('/locations/' . $city->slug . '/') }}" class="card-link">Browse {{ $city->name }} &rarr;</a>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endforeach

<section class="seo-cta-final">
    <div class="container">
        <h2>Need Help Right Now?</h2>
        <p>Take our 5-minute quiz and get matched with verified domestic staff in your area.</p>
        <a href="{{ url('/onboarding') }}" class="btn-primary btn-large">Start Matching Now &rarr;</a>
        <p class="guarantee-note">Protected by our 10-day money-back guarantee.</p>
    </div>
</section>

@endsection
