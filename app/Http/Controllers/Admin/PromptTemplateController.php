<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentPromptTemplate;
use App\Services\KnowledgeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PromptTemplateController extends Controller
{
    public function __construct(private readonly KnowledgeService $knowledge)
    {
    }

    /**
     * List all prompt templates grouped by agent name.
     */
    public function index()
    {
        $templates = AgentPromptTemplate::with('editor')
            ->orderBy('agent_name')
            ->orderBy('tier')
            ->get()
            ->groupBy('agent_name');

        return Inertia::render('Admin/Agent/Prompts/Index', [
            'templates' => $templates,
            'agents' => ['ambassador', 'scout', 'sentinel', 'referee', 'concierge', 'treasurer', 'gatekeeper'],
            'tiers' => ['guest', 'lead', 'authenticated', 'admin'],
        ]);
    }

    /**
     * Show the editor for a single prompt template.
     */
    public function edit(AgentPromptTemplate $template)
    {
        return Inertia::render('Admin/Agent/Prompts/Edit', [
            'template' => $template->load('editor'),
        ]);
    }

    /**
     * Save a new version of the prompt.
     */
    public function update(Request $request, AgentPromptTemplate $template)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:150',
            'system_prompt' => 'required|string|min:50',
        ]);

        $template->saveNewVersion($validated['system_prompt'], auth()->id());
        $template->update(['label' => $validated['label']]);

        $this->knowledge->flushCache($template->agent_name, $template->tier);

        return redirect()
            ->route('admin.agent.prompts.index')
            ->with('success', "Prompt for {$template->agent_name}/{$template->tier} updated to v{$template->version}.");
    }

    /**
     * Roll back to the previous version of a prompt.
     */
    public function rollback(AgentPromptTemplate $template)
    {
        try {
            $template->rollback();
            $this->knowledge->flushCache($template->agent_name, $template->tier);

            return back()->with('success', 'Rolled back to previous version.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new prompt template.
     */
    public function create()
    {
        return Inertia::render('Admin/Agent/Prompts/Create', [
            'agents' => ['ambassador', 'scout', 'sentinel', 'referee', 'concierge', 'treasurer', 'gatekeeper'],
            'tiers' => ['guest', 'lead', 'authenticated', 'admin'],
        ]);
    }

    /**
     * Create a brand new template (for a new agent+tier combination).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'agent_name' => 'required|string|in:ambassador,scout,sentinel,referee,concierge,treasurer,gatekeeper',
            'tier' => 'required|string|in:guest,lead,authenticated,admin',
            'label' => 'required|string|max:150',
            'system_prompt' => 'required|string|min:50',
        ]);

        AgentPromptTemplate::where('agent_name', $validated['agent_name'])
            ->where('tier', $validated['tier'])
            ->update(['is_active' => false]);

        $template = AgentPromptTemplate::create([
            ...$validated,
            'version' => 1,
            'is_active' => true,
            'updated_by' => auth()->id(),
        ]);

        $this->knowledge->flushCache($template->agent_name, $template->tier);

        return redirect()
            ->route('admin.agent.prompts.index')
            ->with('success', "New prompt template created for {$template->agent_name}/{$template->tier}.");
    }
}