@extends('seo.layouts.master')

@section('content')

<section class="seo-hero">
    <div class="container">
        <h1>{{ $page->h1 }}</h1>

        <div class="answer-block" itemprop="description">
            <p>{{ $content['intro'] ?? 'Find verified ' . ($service->name ?? 'domestic staff') . ' in ' . ($location->full_name ?? 'Nigeria') . ' through Maids.ng. Our AI matches you with NIN-verified candidates, backed by a 10-day money-back guarantee.' }}</p>
        </div>

        <div class="trust-bar">
            <span>&#10003; NIN-Verified Staff</span>
            <span>&#10003; Background Checked</span>
            <span>&#10003; 10-Day Money-Back Guarantee</span>
            <span>&#10003; AI-Matched in Minutes</span>
        </div>

        <a href="{{ $quizUrl ?? url('/?utm_source=seo&service=' . ($service->slug ?? '') . '&location=' . ($location->slug ?? '')) }}"
           class="btn-primary cta-quiz">
            Find a {{ $service->name ?? 'Helper' }} in {{ $location->name ?? 'Nigeria' }} &rarr;
        </a>

        <p class="price-anchor">
            From <strong>&#8358;{{ number_format($salary['min']) }}</strong> per month.
            Salary range: &#8358;{{ number_format($salary['min']) }} &#8211; &#8358;{{ number_format($salary['max']) }}.
        </p>
    </div>
</section>

<section class="seo-section">
    <div class="container">
        <h2>What Does a {{ $service->name }} in {{ $location->name }} Do?</h2>
        <p>{{ $content['what_is_this'] ?? $service->short_description }}</p>
        <p>{{ $service->duties }}</p>
    </div>
</section>

<section class="seo-section">
    <div class="container">
        <h2>Hiring a {{ $service->name }} in {{ $location->name }}</h2>
        <p>{{ $content['local_context'] ?? 'Families in ' . $location->full_name . ' rely on verified domestic staff to manage busy households. Whether you live in a gated estate or an apartment complex, finding trustworthy help is essential.' }}</p>
    </div>
</section>

<section class="seo-section" id="salary">
    <div class="container">
        <h2>{{ $service->name }} Salary in {{ $location->full_name }} (2025)</h2>
        <p>{{ $content['salary_section'] ?? 'Salary ranges depend on experience, duties, and whether the position is live-in or live-out. Below are current market rates for ' . $location->full_name . '.' }}</p>

        <table class="salary-table" itemscope itemtype="https://schema.org/PriceSpecification">
            <thead>
                <tr>
                    <th>Employment Type</th>
                    <th>Monthly Salary Range</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Full-time (Live-in)</td>
                    <td itemprop="price">&#8358;{{ number_format($salary['min']) }} &#8211; &#8358;{{ number_format(round($salary['max'] * 0.7)) }}</td>
                </tr>
                <tr>
                    <td>Full-time (Live-out)</td>
                    <td>&#8358;{{ number_format(round($salary['min'] * 1.1)) }} &#8211; &#8358;{{ number_format($salary['max']) }}</td>
                </tr>
                @if($service->part_time_available)
                <tr>
                    <td>Part-time</td>
                    <td>&#8358;{{ number_format(round($salary['min'] * 0.5)) }} &#8211; &#8358;{{ number_format(round($salary['min'] * 0.8)) }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        <p class="salary-note"><em>Salary ranges reflect market rates in {{ $location->full_name }} as of 2025. Exact pay depends on experience, duties, and working hours.</em></p>
    </div>
</section>

<section class="seo-section seo-how-it-works">
    <div class="container">
        <h2>How to Find a {{ $service->name }} in {{ $location->name }} with Maids.ng</h2>
        @php $matchingFee = number_format((int) \App\Models\Setting::get('matching_fee_amount', 5000)); @endphp
        <ol class="steps-list">
            <li><strong>Take the 5-minute quiz</strong> &#8212; Tell us what kind of help you need, your schedule, location, and budget.</li>
            <li><strong>See your top matches</strong> &#8212; Our AI matches you with verified {{ $service->plural ?? $service->name . 's' }} in {{ $location->name }}.</li>
            <li><strong>Pay the matching fee</strong> &#8212; &#8358;{{ $matchingFee }} one-time fee, covered by our 10-day guarantee.</li>
            <li><strong>Connect and hire</strong> &#8212; Get the {{ $service->name }}'s contact details and arrange a start date.</li>
        </ol>
        <p>{{ $content['why_maids_ng'] ?? 'Maids.ng uses AI matching and NIN verification to connect you with the right domestic staff. Every candidate is background-checked and verified.' }}</p>
        <a href="{{ url('/onboarding') }}" class="btn-secondary">Start the Quiz Free &rarr;</a>
    </div>
</section>

@if(!empty($content['hiring_tips']) && is_array($content['hiring_tips']))
<section class="seo-section">
    <div class="container">
        <h2>Tips for Hiring a {{ $service->name }} in {{ $location->name }}</h2>
        @foreach($content['hiring_tips'] as $tip)
        <div class="tip-block">
            <h3>{{ $tip['tip'] }}</h3>
            <p>{{ $tip['explanation'] }}</p>
        </div>
        @endforeach
    </div>
</section>
@endif

<section class="seo-section seo-verification">
    <div class="container">
        <h2>Why Verification Matters When Hiring {{ $service->plural ?? $service->name . 's' }} in {{ $location->name }}</h2>
        <p>{{ $content['what_to_check'] ?? 'Always verify NIN, check references, and start with a trial period before committing to a full-time arrangement.' }}</p>
        <p>All {{ $service->plural ?? $service->name . 's' }} on Maids.ng are required to complete NIN (National Identification Number) verification before their profile appears in search results. This means you can see a government-verified identity for every match.</p>
    </div>
</section>

<div class="eeat-block">
    <div class="container">
        <h3>About This Information</h3>
        <p>This hiring and salary guide is prepared by the Maids.ng team based on:</p>
        <ul>
            <li>Live platform data from employer-maid matches on Maids.ng</li>
            <li>Market research conducted across Lagos, Abuja, and Port Harcourt ({{ date('Y') }})</li>
            <li>Salary data reported by domestic workers and employers on our platform</li>
        </ul>
        <p>All pricing figures reflect market conditions as of {{ date('F Y') }}. We update this guide quarterly.</p>
    </div>
</div>

@if(!empty($content['faqs']) && is_array($content['faqs']))
<section class="seo-section seo-faq" id="faq">
    <div class="container">
        <h2>Frequently Asked Questions &#8212; {{ $service->name }} in {{ $location->name }}</h2>

        <div class="faq-list" itemscope itemtype="https://schema.org/FAQPage">
            @foreach($content['faqs'] as $faq)
            <div class="faq-item" itemscope itemtype="https://schema.org/Question" itemprop="mainEntity">
                <h3 itemprop="name">{{ $faq['question'] }}</h3>
                <div itemscope itemtype="https://schema.org/Answer" itemprop="acceptedAnswer">
                    <p class="faq-short-answer" itemprop="text">{{ $faq['short_answer'] }}</p>
                    <div class="faq-full-answer">
                        <p>{{ $faq['full_answer'] }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if(isset($nearbyPages) && $nearbyPages->count() > 0)
<section class="seo-section seo-nearby">
    <div class="container">
        <h2>Also Hiring {{ $service->plural ?? $service->name . 's' }} Near {{ $location->name }}</h2>
        <ul class="nearby-list">
            @foreach($nearbyPages as $nearby)
            <li><a href="{{ url($nearby->url_path) }}">{{ $service->name }} in {{ $nearby->location->name }}</a></li>
            @endforeach
        </ul>
    </div>
</section>
@endif

<section class="seo-cta-final">
    <div class="container">
        <h2>Ready to Find a {{ $service->name }} in {{ $location->name }}?</h2>
        <p>{{ $content['cta_text'] ?? 'Take our 5-minute quiz and get matched with verified ' . ($service->plural ?? $service->name . 's') . ' in ' . $location->name . ' today.' }}</p>
        <a href="{{ $quizUrl ?? url('/onboarding') }}"
           class="btn-primary btn-large">
            Find Your {{ $service->name }} Now &rarr;
        </a>
        <p class="guarantee-note">Protected by our 10-day money-back guarantee.</p>
    </div>
</section>

@endsection
