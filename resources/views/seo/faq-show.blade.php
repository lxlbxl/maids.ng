@extends('seo.layouts.master')

@section('content')

<section class="seo-hero">
    <div class="container">
        <h1>{{ $page->h1 }}</h1>
        <div class="answer-block"><p>{{ $page->content_blocks['intro'] ?? $faq->short_answer }}</p></div>
    </div>
</section>

<section class="seo-section">
    <div class="container">
        <h2>{{ $faq->question }}</h2>
        <p>{{ $faq->answer }}</p>
    </div>
</section>

@if($relatedFaqs->count() > 0)
<section class="seo-section seo-faq">
    <div class="container">
        <h2>Related Questions</h2>
        <div class="faq-list">
            @foreach($relatedFaqs as $rf)
            <div class="faq-item">
                <h3><a href="{{ url('/faq/' . $rf->slug . '/') }}" style="color:inherit;text-decoration:none;">{{ $rf->question }}</a></h3>
                <p class="faq-short-answer">{{ $rf->short_answer }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="seo-cta-final">
    <div class="container">
        <h2>Need More Help?</h2>
        <p>Find verified domestic staff or get your questions answered with Maids.ng.</p>
        <a href="{{ url('/onboarding') }}" class="btn-primary btn-large">Start Free &rarr;</a>
    </div>
</section>

@endsection
