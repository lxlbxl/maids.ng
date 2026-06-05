@extends('seo.layouts.master')

@section('content')

@php
    $page = (object)[
        'meta_title' => 'Frequently Asked Questions — Maids.ng',
        'meta_description' => 'Answers to common questions about hiring domestic staff in Nigeria. Salary guides, verification, hiring process, and more.',
        'url_path' => '/faq/',
        'h1' => 'Frequently Asked Questions',
        'schema_markup' => null,
    ];
@endphp

<section class="seo-hero">
    <div class="container">
        <h1>{{ $page->h1 }}</h1>
        <div class="answer-block">
            <p>Find answers to the most common questions about hiring domestic staff in Nigeria. From salary guides to NIN verification, we cover everything you need to know.</p>
        </div>
    </div>
</section>

@foreach($categories as $cat)
    @if(isset($faqsByCategory[$cat]) && $faqsByCategory[$cat]->count() > 0)
    <section class="seo-section">
        <div class="container">
            <h2>{{ ucwords(str_replace('_', ' ', $cat)) }}</h2>
            <div class="faq-list">
                @foreach($faqsByCategory[$cat] as $faq)
                <div class="faq-item">
                    <h3><a href="{{ url('/faq/' . $faq->slug . '/') }}" style="color:inherit;text-decoration:none;">{{ $faq->question }}</a></h3>
                    <p class="faq-short-answer">{{ $faq->short_answer }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif
@endforeach

<section class="seo-cta-final">
    <div class="container">
        <h2>Still Have Questions?</h2>
        <p>Our team is here to help. Contact us or start the matching process today.</p>
        <a href="{{ url('/contact') }}" class="btn-primary btn-large">Contact Us &rarr;</a>
    </div>
</section>

@endsection
