<?php

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Models\{SeoPage, SeoLocation, SeoService, SeoFaq};
use App\Services\SeoContentGenerator;
use App\Jobs\{GenerateSeoPageRegistry, RefreshSeoContent};
use Illuminate\Http\Request;
use Inertia\Inertia;

class SeoDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_pages' => SeoPage::count(),
            'published' => SeoPage::where('page_status', 'published')->count(),
            'draft' => SeoPage::where('page_status', 'draft')->count(),
            'noindex' => SeoPage::where('page_status', 'noindex')->count(),
            'total_locations' => SeoLocation::count(),
            'total_services' => SeoService::count(),
            'total_faqs' => SeoFaq::count(),
        ];

        $pagesByType = SeoPage::selectRaw('page_type, page_status, count(*) as cnt')
            ->groupBy('page_type', 'page_status')
            ->get()
            ->groupBy('page_type')
            ->map(fn($group) => $group->mapWithKeys(fn($row) => [$row->page_status => $row->cnt]));

        $recentPages = SeoPage::with(['location', 'service'])
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        return Inertia::render('Admin/Seo/Dashboard', compact('stats', 'pagesByType', 'recentPages'));
    }

    public function pages(Request $request)
    {
        $query = SeoPage::with(['location', 'service']);

        if ($request->filled('type')) {
            $query->where('page_type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('page_status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('h1', 'like', '%' . $request->search . '%')
                  ->orWhere('url_path', 'like', '%' . $request->search . '%');
            });
        }

        $pages = $query->orderByDesc('updated_at')->paginate(50);

        return Inertia::render('Admin/Seo/Pages', [
            'pages' => $pages,
            'filters' => $request->only(['type', 'status', 'search']),
        ]);
    }

    public function showPage($id)
    {
        $page = SeoPage::with(['location', 'service'])->findOrFail($id);

        return Inertia::render('Admin/Seo/PageDetail', [
            'page' => $page,
        ]);
    }

    public function regenerateContent($id, SeoContentGenerator $generator)
    {
        $page = SeoPage::findOrFail($id);
        $generator->generate($page);

        return back()->with('success', 'Content regenerated for page: ' . $page->h1);
    }

    public function bulkGenerate()
    {
        GenerateSeoPageRegistry::dispatch();
        return back()->with('success', 'Page registry generation queued.');
    }

    public function bulkRefreshContent()
    {
        RefreshSeoContent::dispatch();
        return back()->with('success', 'Content refresh queued for draft/old pages.');
    }

    public function locations()
    {
        $locations = SeoLocation::with('parent')
            ->orderBy('tier')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Seo/Locations', compact('locations'));
    }

    public function services()
    {
        $services = SeoService::orderBy('demand_index', 'desc')->get();

        return Inertia::render('Admin/Seo/Services', compact('services'));
    }
}
