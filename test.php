<?php
try {
    $templates = \App\Models\AgentPromptTemplate::with('editor')->orderBy('agent_name')->orderBy('tier')->get()->groupBy('agent_name');
    echo "PROMPTS OK\n";
    $articles = \App\Models\AgentKnowledgeBase::with('editor')->orderBy('priority')->orderBy('category')->get();
    echo "KNOWLEDGE OK\n";
    
    // Check if the Inertia components exist
    if (!file_exists(resource_path('js/Pages/Admin/Agent/Prompts/Index.jsx'))) {
        echo "MISSING PROMPT VIEW\n";
    }
    if (!file_exists(resource_path('js/Pages/Admin/Agent/Knowledge/Index.jsx'))) {
        echo "MISSING KNOWLEDGE VIEW\n";
    }
    
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}
