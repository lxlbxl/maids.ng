@extends('seo.layouts.master')
@section('content')
<section class="seo-hero">
    <div class="container">
        <h1>{{ $page->h1 }}</h1>
        <div class="answer-block"><p>{{ $page->content_blocks['intro'] ?? '' }}</p></div>
        <a href="{{ url('/onboarding') }}" class="btn-primary">Find Help Now &rarr;</a>
    </div>
</section>
@if(!empty($page->content_blocks['full_content']))
<section class="seo-section">
    <div class="container">
        {!! $page->content_blocks['full_content'] !!}
    </div>
</section>
@endif
@if(!empty($page->content_blocks['faqs']))
<section class="seo-section seo-faq">
    <div class="container">
        <h2>Frequently Asked Questions</h2>
        <div class="faq-list">
            @foreach($page->content_blocks['faqs'] as $faq)
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
        <h2>Need Domestic Staff?</h2>
        <p>{{ $page->content_blocks['cta_text'] ?? 'Get matched with verified domestic staff in under 5 minutes.' }}</p>
        <a href="{{ url('/onboarding') }}" class="btn-primary btn-large">Start Free &rarr;</a>
    </div>
</section>
@endsection
