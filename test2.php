<?php
try {
    $templates = \App\Models\AgentPromptTemplate::with('editor')->orderBy('agent_name')->orderBy('tier')->get()->groupBy('agent_name')->toArray();
    echo "PROMPTS JSON OK\n";
    $articles = \App\Models\AgentKnowledgeBase::with('editor')->orderBy('priority')->orderBy('category')->get()->toArray();
    echo "KNOWLEDGE JSON OK\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}
