<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentKnowledgeBase;
use App\Services\KnowledgeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KnowledgeBaseController extends Controller
{
    public function __construct(private readonly KnowledgeService $knowledge)
    {
    }

    public function index(Request $request)
    {
        $articles = AgentKnowledgeBase::with('editor')
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->orderBy('priority')
            ->orderBy('category')
            ->paginate(25);

        return Inertia::render('Admin/Agent/Knowledge/Index', [
            'articles' => $articles,
            'categories' => ['policy', 'faq', 'procedure', 'legal', 'restriction', 'onboarding', 'pricing'],
            'filters' => $request->only(['category', 'search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Agent/Knowledge/Create', [
            'categories' => ['policy', 'faq', 'procedure', 'legal', 'restriction', 'onboarding', 'pricing'],
            'agents' => ['all', 'ambassador', 'scout', 'sentinel', 'referee', 'concierge', 'treasurer', 'gatekeeper'],
            'tiers' => ['all', 'guest', 'lead', 'authenticated', 'admin'],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'title' => 'required|string|max:200',
            'content' => 'required|string|min:10',
            'applies_to' => 'required|array|min:1',
            'applies_to.*' => 'string',
            'visible_to_tiers' => 'required|array|min:1',
            'visible_to_tiers.*' => 'string',
            'priority' => 'required|integer|min:1|max:999',
            'is_active' => 'boolean',
        ]);

        AgentKnowledgeBase::create([
            ...$validated,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->knowledge->flushCache();

        return redirect()
            ->route('admin.agent.knowledge.index')
            ->with('success', 'Knowledge base article created. Cache flushed — agents updated immediately.');
    }

    public function edit(AgentKnowledgeBase $article)
    {
        return Inertia::render('Admin/Agent/Knowledge/Edit', [
            'article' => $article,
            'categories' => ['policy', 'faq', 'procedure', 'legal', 'restriction', 'onboarding', 'pricing'],
            'agents' => ['all', 'ambassador', 'scout', 'sentinel', 'referee', 'concierge', 'treasurer', 'gatekeeper'],
            'tiers' => ['all', 'guest', 'lead', 'authenticated', 'admin'],
        ]);
    }

    public function update(Request $request, AgentKnowledgeBase $article)
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'title' => 'required|string|max:200',
            'content' => 'required|string|min:10',
            'applies_to' => 'required|array|min:1',
            'applies_to.*' => 'string',
            'visible_to_tiers' => 'required|array|min:1',
            'visible_to_tiers.*' => 'string',
            'priority' => 'required|integer|min:1|max:999',
            'is_active' => 'boolean',
        ]);

        $article->update([
            ...$validated,
            'updated_by' => auth()->id(),
        ]);

        $this->knowledge->flushCache();

        return redirect()
            ->route('admin.agent.knowledge.index')
            ->with('success', 'Article updated. Agents will use new content immediately.');
    }

    public function destroy(AgentKnowledgeBase $article)
    {
        $article->update(['is_active' => false]);
        $this->knowledge->flushCache();

        return back()->with('success', 'Article deactivated.');
    }
}