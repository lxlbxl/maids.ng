<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\{SeoFaq, SeoPage};

class SeoFaqController extends Controller
{
    public function hub()
    {
        $categories = ['pricing', 'process', 'verification', 'legal', 'service_type', 'platform', 'salary', 'general'];

        $faqsByCategory = [];
        foreach ($categories as $cat) {
            $faqsByCategory[$cat] = SeoFaq::where('category', $cat)
                ->where('is_active', true)
                ->orderBy('question')
                ->get();
        }

        return response()->view('seo.faq-hub', compact('faqsByCategory', 'categories'));
    }

    public function show($slug)
    {
        $faq = SeoFaq::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $page = SeoPage::where('page_type', 'faq')
            ->where('url_path', "/faq/{$slug}/")
            ->first();

        if (!$page) {
            $page = SeoPage::create([
                'page_type' => 'faq',
                'url_path' => "/faq/{$slug}/",
                'h1' => $faq->question,
                'meta_title' => $faq->question . ' | Maids.ng',
                'meta_description' => substr($faq->short_answer, 0, 155),
                'page_status' => 'published',
                'content_blocks' => ['question' => $faq->question, 'answer' => $faq->answer, 'short_answer' => $faq->short_answer],
            ]);
        }

        $relatedFaqs = SeoFaq::where('category', $faq->category)
            ->where('id', '!=', $faq->id)
            ->where('is_active', true)
            ->inRandomOrder()
            ->take(5)
            ->get();

        return response()->view('seo.faq-show', compact('faq', 'page', 'relatedFaqs'));
    }
}
