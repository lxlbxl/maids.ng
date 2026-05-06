@extends('seo.layouts.master')
@section('content')
<section class="seo-hero">
    <div class="container">
        <h1>{{ $page->h1 }}</h1>
        <div class="answer-block"><p>{{ $page->content_blocks['intro'] ?? 'Complete salary guide for ' . ($service->name ?? 'domestic staff') . ' in ' . ($city->name ?? 'Nigeria') . '.' }}</p></div>
        <a href="{{ url('/onboarding') }}" class="btn-primary">Find Help Now &rarr;</a>
    </div>
</section>
<section class="seo-section" id="salary">
    <div class="container">
        <h2>{{ $service->name }} Salary in {{ $city->name }} (2025)</h2>
        <p>{{ $page->content_blocks['salary_section'] ?? '' }}</p>
        <table class="salary-table">
            <thead><tr><th>Employment Type</th><th>Monthly Salary Range</th></tr></thead>
            <tbody>
                <tr><td>Full-time (Live-in)</td><td>&#8358;{{ number_format($salary['min']) }} &#8211; &#8358;{{ number_format(round($salary['max'] * 0.7)) }}</td></tr>
                <tr><td>Full-time (Live-out)</td><td>&#8358;{{ number_format(round($salary['min'] * 1.1)) }} &#8211; &#8358;{{ number_format($salary['max']) }}</td></tr>
                @if($service->part_time_available)
                <tr><td>Part-time</td><td>&#8358;{{ number_format(round($salary['min'] * 0.5)) }} &#8211; &#8358;{{ number_format(round($salary['min'] * 0.8)) }}</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</section>
<section class="seo-cta-final">
    <div class="container">
        <h2>Find a {{ $service->name }} in {{ $city->name }}</h2>
        <a href="{{ url('/onboarding') }}" class="btn-primary btn-large">Start Free &rarr;</a>
    </div>
</section>
@endsection
