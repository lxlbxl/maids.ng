@extends('seo.layouts.master')

@section('content')

<section class="seo-hero">
    <div class="container">
        <h1>{{ $page->h1 ?? 'Verified Domestic Staff in ' . $city->name }}</h1>
        <div class="answer-block">
            <p>{{ $page->content_blocks['intro'] ?? 'Find verified housekeepers, nannies, cooks, and drivers in ' . $city->name . '. Maids.ng connects you with NIN-verified domestic staff, backed by our 10-day money-back guarantee.' }}</p>
        </div>
        <div class="trust-bar">
            <span>&#10003; NIN-Verified Staff</span>
            <span>&#10003; Background Checked</span>
            <span>&#10003; 10-Day Guarantee</span>
        </div>
        <a href="{{ url('/?utm_source=seo&location=' . $city->slug) }}" class="btn-primary">
            Find Staff in {{ $city->name }} &rarr;
        </a>
    </div>
</section>

@if($city->description)
<section class="seo-section">
    <div class="container">
        <h2>About {{ $city->name }}</h2>
        <p>{{ $city->description }}</p>
        @if($city->demand_context)
        <p>{{ $city->demand_context }}</p>
        @endif
    </div>
</section>
@endif

<section class="seo-section">
    <div class="container">
        <h2>Find Domestic Staff by Service in {{ $city->name }}</h2>
        <div class="hub-grid">
            @foreach($servicePages as $sp)
            <div class="hub-card">
                <h3><a href="{{ url($sp->url_path) }}">{{ $sp->service->name }} in {{ $city->name }}</a></h3>
                <p>From &#8358;{{ number_format($sp->service->getSalaryForCity($city->slug)['min']) }}/month</p>
                <a href="{{ url($sp->url_path) }}" class="card-link">View matches &rarr;</a>
            </div>
            @endforeach
        </div>
    </div>
</section>

@if($areas->count() > 0)
<section class="seo-section">
    <div class="container">
        <h2>Areas in {{ $city->name }}</h2>
        <div class="hub-grid">
            @foreach($areas as $area)
            <div class="hub-card">
                <h3><a href="{{ url('/locations/' . $city->slug . '/' . $area->slug . '/') }}">{{ $area->name }}</a></h3>
                <a href="{{ url('/locations/' . $city->slug . '/' . $area->slug . '/') }}" class="card-link">Browse {{ $area->name }} &rarr;</a>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="seo-cta-final">
    <div class="container">
        <h2>Ready to Find Help in {{ $city->name }}?</h2>
        <p>Take our 5-minute quiz and get matched with verified domestic staff in {{ $city->name }}.</p>
        <a href="{{ url('/onboarding') }}" class="btn-primary btn-large">Start Free &rarr;</a>
        <p class="guarantee-note">Protected by our 10-day money-back guarantee.</p>
    </div>
</section>

@endsection
