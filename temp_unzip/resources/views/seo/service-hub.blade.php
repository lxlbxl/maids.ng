@extends('seo.layouts.master')

@section('content')

<section class="seo-hero">
    <div class="container">
        <h1>{{ $page->h1 }}</h1>
        <div class="answer-block">
            <p>{{ $content['intro'] ?? 'Find verified ' . ($service->plural ?? $service->name . 's') . ' across Nigeria through Maids.ng. Our AI matches you with NIN-verified candidates, backed by a 10-day money-back guarantee.' }}</p>
        </div>
        <div class="trust-bar">
            <span>&#10003; NIN-Verified Staff</span>
            <span>&#10003; Background Checked</span>
            <span>&#10003; 10-Day Money-Back Guarantee</span>
        </div>
        <a href="{{ url('/?utm_source=seo&service=' . $service->slug) }}" class="btn-primary">
            Find a {{ $service->name }} Near You &rarr;
        </a>
        <p class="price-anchor">
            From <strong>&#8358;{{ number_format($service->salary_min) }}</strong>/month nationwide.
        </p>
    </div>
</section>

<section class="seo-section">
    <div class="container">
        <h2>What Does a {{ $service->name }} Do?</h2>
        <p>{{ $content['what_is_this'] ?? $service->short_description }}</p>
        <p>{{ $service->duties }}</p>
    </div>
</section>

<section class="seo-section">
    <div class="container">
        <h2>Find {{ $service->plural ?? $service->name . 's' }} by City</h2>
        <div class="hub-grid">
            @foreach($cityPages as $cp)
            <div class="hub-card">
                <h3><a href="{{ url($cp->url_path) }}">{{ $service->name }} in {{ $cp->location->name }}</a></h3>
                <p>From &#8358;{{ number_format($service->getSalaryForCity($cp->location->slug)['min']) }}/month</p>
                <a href="{{ url($cp->url_path) }}" class="card-link">View matches &rarr;</a>
            </div>
            @endforeach
        </div>
    </div>
</section>

@if(!empty($content['faqs']) && is_array($content['faqs']))
<section class="seo-section seo-faq" id="faq">
    <div class="container">
        <h2>Frequently Asked Questions &#8212; {{ $service->name }} in Nigeria</h2>
        <div class="faq-list" itemscope itemtype="https://schema.org/FAQPage">
            @foreach($content['faqs'] as $faq)
            <div class="faq-item">
                <h3>{{ $faq['question'] }}</h3>
                <p class="faq-short-answer">{{ $faq['short_answer'] }}</p>
                <div class="faq-full-answer"><p>{{ $faq['full_answer'] }}</p></div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="seo-cta-final">
    <div class="container">
        <h2>Ready to Find a {{ $service->name }}?</h2>
        <p>{{ $content['cta_text'] ?? 'Start free and get matched in under 5 minutes.' }}</p>
        <a href="{{ url('/onboarding') }}" class="btn-primary btn-large">Find Your {{ $service->name }} Now &rarr;</a>
        <p class="guarantee-note">Protected by our 10-day money-back guarantee.</p>
    </div>
</section>

@endsection
