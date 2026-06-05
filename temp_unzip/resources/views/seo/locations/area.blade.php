@extends('seo.layouts.master')

@section('content')

@php
    $pageData = $page ?? (object)[
        'meta_title' => 'Hire Domestic Staff in ' . $area->name . ' | Maids.ng',
        'meta_description' => 'Find verified housekeepers, nannies, and cooks in ' . $area->name . ', ' . $area->parent->name . '. Background-checked. 10-day guarantee.',
        'url_path' => '/locations/' . $area->parent->slug . '/' . $area->slug . '/',
        'h1' => 'Domestic Staff in ' . $area->name . ', ' . $area->parent->name,
        'content_blocks' => [],
        'schema_markup' => null,
    ];
    $location = $area;
    $content = $pageData->content_blocks ?? [];
@endphp

<section class="seo-hero">
    <div class="container">
        <h1>{{ $pageData->h1 }}</h1>
        <div class="answer-block">
            <p>{{ $content['intro'] ?? 'Find verified domestic staff in ' . $area->name . ', ' . $area->parent->name . '. Maids.ng connects you with NIN-verified housekeepers, nannies, and cooks, backed by our 10-day money-back guarantee.' }}</p>
        </div>
        <div class="trust-bar">
            <span>&#10003; NIN-Verified Staff</span>
            <span>&#10003; Background Checked</span>
            <span>&#10003; 10-Day Guarantee</span>
        </div>
        <a href="{{ url('/?utm_source=seo&location=' . $area->slug) }}" class="btn-primary">
            Find Staff in {{ $area->name }} &rarr;
        </a>
    </div>
</section>

@if($area->description)
<section class="seo-section">
    <div class="container">
        <h2>About {{ $area->name }}</h2>
        <p>{{ $area->description }}</p>
    </div>
</section>
@endif

<section class="seo-section">
    <div class="container">
        <h2>Services Available in {{ $area->name }}</h2>
        <div class="hub-grid">
            @php $services = \App\Models\SeoService::where('is_active', true)->get(); @endphp
            @foreach($services as $svc)
            <div class="hub-card">
                <h3><a href="{{ url('/find/' . $svc->slug . '-in-' . $area->slug . '-' . $area->parent->slug . '/') }}">{{ $svc->name }} in {{ $area->name }}</a></h3>
                <a href="{{ url('/find/' . $svc->slug . '-in-' . $area->slug . '-' . $area->parent->slug . '/') }}" class="card-link">Find {{ $svc->name }} &rarr;</a>
            </div>
            @endforeach
        </div>
    </div>
</section>

@if(isset($nearbyAreas) && $nearbyAreas->count() > 0)
<section class="seo-section">
    <div class="container">
        <h2>Nearby Areas</h2>
        <ul class="nearby-list">
            @foreach($nearbyAreas as $na)
            <li><a href="{{ url('/locations/' . $area->parent->slug . '/' . $na->slug . '/') }}">{{ $na->name }}</a></li>
            @endforeach
        </ul>
    </div>
</section>
@endif

<section class="seo-cta-final">
    <div class="container">
        <h2>Ready to Find Help in {{ $area->name }}?</h2>
        <p>Take our 5-minute quiz and get matched with verified domestic staff near you.</p>
        <a href="{{ url('/onboarding') }}" class="btn-primary btn-large">Start Free &rarr;</a>
        <p class="guarantee-note">Protected by our 10-day money-back guarantee.</p>
    </div>
</section>

@endsection
