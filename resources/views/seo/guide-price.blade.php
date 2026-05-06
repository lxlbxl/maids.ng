@extends('seo.layouts.master')

@section('content')

<section class="seo-hero">
    <div class="container">
        <h1>{{ $page->h1 }}</h1>
        <div class="answer-block">
            <p>{{ $page->content_blocks['intro'] ?? 'The average ' . $service->name . ' salary in ' . $city->name . ' ranges from &#8358;' . number_format($salary['min']) . ' to &#8358;' . number_format($salary['max']) . ' per month in 2025. Factors include experience, duties, and employment type.' }}</p>
        </div>
        <a href="{{ url('/onboarding') }}" class="btn-primary">Find a {{ $service->name }} &rarr;</a>
    </div>
</section>

<section class="seo-section" id="salary">
    <div class="container">
        <h2>{{ $service->name }} Salary in {{ $city->name }} (2025)</h2>
        <p>{{ $page->content_blocks['salary_section'] ?? 'Below are the current market rates for ' . $service->name . 's in ' . $city->name . ' as of 2025.' }}</p>

        <table class="salary-table">
            <thead>
                <tr><th>Employment Type</th><th>Monthly Salary Range</th></tr>
            </thead>
            <tbody>
                <tr><td>Full-time (Live-in)</td><td>&#8358;{{ number_format($salary['min']) }} &#8211; &#8358;{{ number_format(round($salary['max'] * 0.7)) }}</td></tr>
                <tr><td>Full-time (Live-out)</td><td>&#8358;{{ number_format(round($salary['min'] * 1.1)) }} &#8211; &#8358;{{ number_format($salary['max']) }}</td></tr>
                @if($service->part_time_available)
                <tr><td>Part-time</td><td>&#8358;{{ number_format(round($salary['min'] * 0.5)) }} &#8211; &#8358;{{ number_format(round($salary['min'] * 0.8)) }}</td></tr>
                @endif
            </tbody>
        </table>
        <p class="salary-note"><em>Salary ranges reflect market rates in {{ $city->name }} as of {{ date('F Y') }}.</em></p>
    </div>
</section>

<div class="eeat-block">
    <div class="container">
        <h3>About This Salary Guide</h3>
        <p>This salary guide is prepared by Maids.ng based on live platform data and market research across Nigeria ({{ date('Y') }}). Updated quarterly.</p>
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
        <h2>Find a {{ $service->name }} in {{ $city->name }}</h2>
        <p>{{ $page->content_blocks['cta_text'] ?? 'Get matched with verified ' . $service->plural . ' in ' . $city->name . ' today.' }}</p>
        <a href="{{ url('/onboarding') }}" class="btn-primary btn-large">Start Free &rarr;</a>
        <p class="guarantee-note">Protected by our 10-day money-back guarantee.</p>
    </div>
</section>

@endsection
