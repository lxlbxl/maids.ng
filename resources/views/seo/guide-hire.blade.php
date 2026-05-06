@extends('seo.layouts.master')

@section('content')

<section class="seo-hero">
    <div class="container">
        <h1>{{ $page->h1 }}</h1>
        <div class="answer-block">
            <p>{{ $page->content_blocks['intro'] ?? 'A comprehensive guide to hiring a ' . $service->name . ' in Nigeria. Learn what to look for, how much to pay, and how to verify credentials.' }}</p>
        </div>
        <a href="{{ url('/onboarding') }}" class="btn-primary">Find a {{ $service->name }} Now &rarr;</a>
    </div>
</section>

@if(!empty($page->content_blocks['what_is_this']))
<section class="seo-section">
    <div class="container">
        <h2>What Does a {{ $service->name }} Do?</h2>
        <p>{{ $page->content_blocks['what_is_this'] }}</p>
        <p>{{ $service->duties }}</p>
    </div>
</section>
@endif

<section class="seo-section seo-how-it-works">
    <div class="container">
        <h2>How to Hire a {{ $service->name }} with Maids.ng</h2>
        @php $matchingFee = number_format((int) \App\Models\Setting::get('matching_fee_amount', 5000)); @endphp
        <ol class="steps-list">
            <li><strong>Take the 5-minute quiz</strong> &#8212; Tell us what kind of help you need.</li>
            <li><strong>See your top matches</strong> &#8212; Our AI returns verified {{ $service->plural }} ranked by compatibility.</li>
            <li><strong>Pay the matching fee</strong> &#8212; &#8358;{{ $matchingFee }} one-time, covered by our 10-day guarantee.</li>
            <li><strong>Connect and hire</strong> &#8212; Get contact details and arrange a start date.</li>
        </ol>
    </div>
</section>

@if(!empty($page->content_blocks['hiring_tips']))
<section class="seo-section">
    <div class="container">
        <h2>Tips for Hiring a {{ $service->name }}</h2>
        @foreach($page->content_blocks['hiring_tips'] as $tip)
        <div class="tip-block">
            <h3>{{ $tip['tip'] }}</h3>
            <p>{{ $tip['explanation'] }}</p>
        </div>
        @endforeach
    </div>
</section>
@endif

<div class="eeat-block">
    <div class="container">
        <h3>About This Guide</h3>
        <p>This guide is prepared by the Maids.ng team based on live platform data and market research across Nigeria ({{ date('Y') }}). We update this guide quarterly.</p>
    </div>
</div>

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
        <h2>Ready to Hire a {{ $service->name }}?</h2>
        <p>{{ $page->content_blocks['cta_text'] ?? 'Start free and get matched in under 5 minutes.' }}</p>
        <a href="{{ url('/onboarding') }}" class="btn-primary btn-large">Find Your {{ $service->name }} Now &rarr;</a>
        <p class="guarantee-note">Protected by our 10-day money-back guarantee.</p>
    </div>
</section>

@endsection
