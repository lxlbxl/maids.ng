<?php

namespace App\Http\Controllers\Admin\AgentControlRoom;

use App\Http\Controllers\Controller;
use App\Models\AgentConversation;
use App\Models\AgentEvent;
use App\Models\AgentOverride;
use App\Models\Setting;
use App\Services\Agents\AmbassadorAgent;
use App\Services\Agents\DTOs\InboundMessage;
use App\Services\Ai\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AgentDiagnosticsController extends Controller
{
    /**
     * Full system health diagnostic — DB, tables, AI providers, agents.
     */
    public function systemHealth(): JsonResponse
    {
        $checks = [];
        $allOk = true;

        // 1. Database connectivity
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
            $allOk = false;
        }

        // 2. Required tables
        $requiredTables = [
            'users', 'maid_profiles', 'employer_preferences', 'bookings',
            'agent_channel_identities', 'agent_conversations', 'agent_messages',
            'agent_leads', 'agent_prompt_templates', 'agent_knowledge_base',
            'agent_events', 'agent_overrides', 'human_task_queue',
            'settings', 'wallet_transactions',
        ];
        $missingTables = [];
        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $missingTables[] = $table;
            }
        }
        if (empty($missingTables)) {
            $checks['tables'] = ['status' => 'ok', 'message' => 'All ' . count($requiredTables) . ' required tables present'];
        } else {
            $checks['tables'] = ['status' => 'error', 'message' => 'Missing: ' . implode(', ', $missingTables)];
            $allOk = false;
        }

        // 3. AI Provider configuration
        $activeProvider = Setting::get('ai_active_provider', 'openai');
        $checks['ai_provider'] = ['status' => 'info', 'message' => "Active: {$activeProvider}"];

        $openaiKey = Setting::get('openai_key') ?: env('OPENAI_API_KEY');
        $openrouterKey = Setting::get('openrouter_key') ?: env('OPENROUTER_API_KEY');

        $checks['openai_key'] = $openaiKey
            ? ['status' => 'ok', 'message' => 'Key configured (' . substr($openaiKey, 0, 8) . '...' . substr($openaiKey, -4) . ')']
            : ['status' => 'warning', 'message' => 'Key not configured'];

        $checks['openrouter_key'] = $openrouterKey
            ? ['status' => 'ok', 'message' => 'Key configured (' . substr($openrouterKey, 0, 8) . '...' . substr($openrouterKey, -4) . ')']
            : ['status' => 'warning', 'message' => 'Key not configured'];

        // 4. Prompt templates
        $templateCount = DB::table('agent_prompt_templates')->where('is_active', true)->count();
        $checks['prompt_templates'] = $templateCount > 0
            ? ['status' => 'ok', 'message' => "{$templateCount} active templates"]
            : ['status' => 'warning', 'message' => 'No active prompt templates — agents will use fallback prompts'];

        // 5. Knowledge base
        $kbCount = DB::table('agent_knowledge_base')->where('is_active', true)->count();
        $checks['knowledge_base'] = $kbCount > 0
            ? ['status' => 'ok', 'message' => "{$kbCount} active articles"]
            : ['status' => 'warning', 'message' => 'No knowledge base articles'];

        // 6. Agent overrides
        $overrideCount = AgentOverride::count();
        $checks['agent_overrides'] = $overrideCount > 0
            ? ['status' => 'ok', 'message' => "{$overrideCount} agents registered"]
            : ['status' => 'warning', 'message' => 'No agent overrides found'];

        return response()->json([
            'success' => $allOk,
            'checks' => $checks,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Test an AI provider by making a lightweight API call.
     */
    public function testAiProvider(Request $request): JsonResponse
    {
        $provider = $request->validate(['provider' => 'required|in:openai,openrouter'])['provider'];

        $aiService = new AiService();
        $activeProvider = Setting::get('ai_active_provider', 'openai');

        try {
            $response = $aiService->chat("Say 'OK' and nothing else.", [
                'temperature' => 0,
                'max_tokens' => 10,
            ]);

            if (isset($response['error'])) {
                return response()->json([
                    'success' => false,
                    'provider' => $activeProvider,
                    'message' => $response['message'] ?? $response['error'],
                    'response' => $response,
                ]);
            }

            return response()->json([
                'success' => true,
                'provider' => $activeProvider,
                'message' => 'AI provider responded successfully',
                'reply' => $response['content'] ?? '(no content)',
                'model_used' => $response['raw']['model'] ?? 'unknown',
                'tokens' => $response['usage']['total_tokens'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'provider' => $activeProvider,
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ], 500);
        }
    }

    /**
     * Test a specific agent by name.
     */
    public function testAgent(Request $request): JsonResponse
    {
        $agentName = $request->validate(['agent' => 'required|string'])['agent'];

        $startTime = microtime(true);
        $logs = [];

        try {
            $result = match ($agentName) {
                'ambassador' => $this->testAmbassadorAgent($logs),
                'concierge' => $this->testConciergeAgent($logs),
                'scout' => $this->testScoutAgent($logs),
                'marketer' => $this->testMarketerAgent($logs),
                'seo_content' => $this->testSeoAgent($logs),
                'outreach' => $this->testOutreachAgent($logs),
                default => $this->testGenericAgent($agentName, $logs),
            };

            $duration = round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'success' => true,
                'agent' => $agentName,
                'duration_ms' => $duration,
                'result' => $result,
                'logs' => $logs,
            ]);
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000);

            // Log the full error for admin review
            Log::error("Agent test failed: {$agentName}", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'agent' => $agentName,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'logs' => $logs,
            ], 500);
        }
    }

    /**
     * Test the AmbassadorAgent end-to-end with a real chat message.
     */
    public function testAmbassadorChat(Request $request): JsonResponse
    {
        $message = $request->input('message', 'Hello, what is Maids.ng?');
        $logs = [];

        try {
            $agent = app(AmbassadorAgent::class);
            $logs[] = 'Agent instantiated OK';

            $inbound = InboundMessage::fromWeb([
                'message' => $message,
                'session_id' => 'test_' . uniqid(),
                'phone' => null,
                'email' => null,
                'message_id' => 'test_msg_' . uniqid(),
                'metadata' => ['test' => true],
            ]);
            $logs[] = 'InboundMessage DTO created OK';

            $result = $agent->handle($inbound);
            $logs[] = 'Agent->handle() returned OK';

            return response()->json([
                'success' => true,
                'agent' => 'ambassador',
                'reply' => $result['content'] ?? '(empty)',
                'conversation_id' => $result['conversation_id'] ?? null,
                'tool_calls' => $result['tool_calls'] ?? [],
                '_error' => $result['_error'] ?? null,
                '_file' => $result['_file'] ?? null,
                '_step_logs' => $result['_step_logs'] ?? [],
                '_debug_payload_keys' => $result['_debug_payload_keys'] ?? null,
                '_debug_model' => $result['_debug_model'] ?? null,
                '_debug_model_base' => $result['_debug_model_base'] ?? null,
                'logs' => $logs,
            ]);
        } catch (\Throwable $e) {
            Log::error('AmbassadorAgent chat test failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'agent' => 'ambassador',
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'logs' => $logs,
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Private test methods for each agent
    // ──────────────────────────────────────────────────────────────────

    private function testAmbassadorAgent(array &$logs): array
    {
        $agent = app(AmbassadorAgent::class);
        $logs[] = 'AmbassadorAgent instantiated';

        // Test identity resolution (no DB write)
        $inbound = InboundMessage::fromWeb([
            'message' => 'Test message',
            'session_id' => 'diag_' . uniqid(),
        ]);
        $logs[] = 'InboundMessage DTO created';

        // We do a full handle() which writes to DB — that's the real test
        $result = $agent->handle($inbound);
        $logs[] = 'handle() executed successfully';

        return [
            'reply_preview' => substr($result['content'] ?? '', 0, 200),
            'conversation_id' => $result['conversation_id'] ?? null,
            'has_tool_calls' => !empty($result['tool_calls']),
        ];
    }

    private function testConciergeAgent(array &$logs): array
    {
        $agent = app(\App\Services\Agents\ConciergeAgent::class);
        $logs[] = 'ConciergeAgent instantiated';

        // Find a test user or create a mock scenario
        $user = \App\Models\User::first();
        if (!$user) {
            return ['status' => 'skipped', 'reason' => 'No users in database to test with'];
        }

        $ticket = $agent->processQuery($user, 'What is the matching fee?');
        $logs[] = 'processQuery() executed';

        return [
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
            'agent_handled' => $ticket->agent_handled,
        ];
    }

    private function testScoutAgent(array &$logs): array
    {
        $agent = app(\App\Services\Agents\ScoutAgent::class);
        $logs[] = 'ScoutAgent instantiated';

        $employer = \App\Models\User::where('role', 'employer')->first();
        if (!$employer) {
            return ['status' => 'skipped', 'reason' => 'No employer users found'];
        }

        $prefs = $employer->employerPreferences;
        if (!$prefs) {
            return ['status' => 'skipped', 'reason' => 'Employer has no preferences set'];
        }

        $matches = $agent->findMatches($prefs);
        $logs[] = 'findMatches() executed';

        return [
            'match_count' => count($matches),
            'top_match_score' => $matches[0]['score'] ?? null,
        ];
    }

    private function testMarketerAgent(array &$logs): array
    {
        $agent = app(\App\Services\Agents\MarketerAgent::class);
        $logs[] = 'MarketerAgent instantiated';

        $plan = $agent->generateWeeklyPlan();
        $logs[] = 'generateWeeklyPlan() executed';

        return [
            'plan_days' => count($plan),
            'sample_day' => $plan[0]['day'] ?? null,
        ];
    }

    private function testSeoAgent(array &$logs): array
    {
        $agent = app(\App\Services\Agents\SeoContentAgent::class);
        $logs[] = 'SeoContentAgent instantiated';

        $titles = $agent->generatePageTitles();
        $logs[] = 'generatePageTitles() executed';

        return [
            'titles_generated' => count($titles),
            'sample_title' => $titles[0]['title'] ?? null,
        ];
    }

    private function testOutreachAgent(array &$logs): array
    {
        $agent = app(\App\Services\Agents\OutreachEngine::class);
        $logs[] = 'OutreachEngine instantiated';

        $sequence = $agent->generateSequence('welcome', 'email');
        $logs[] = 'generateSequence() executed';

        return [
            'steps_count' => count($sequence),
            'sample_subject' => $sequence[0]['subject'] ?? null,
        ];
    }

    private function testGenericAgent(string $agentName, array &$logs): array
    {
        $class = 'App\\Services\\Agents\\' . ucfirst($agentName) . 'Agent';
        if (!class_exists($class)) {
            $class = 'App\\Services\\Agents\\' . str_replace('_', '', ucwords($agentName, '_')) . 'Agent';
        }
        if (!class_exists($class)) {
            throw new \RuntimeException("Agent class not found: {$class}");
        }

        $agent = app($class);
        $logs[] = "{$class} instantiated";

        if (method_exists($agent, 'getName')) {
            return ['agent_name' => $agent->getName()];
        }

        return ['status' => 'instantiated', 'class' => $class];
    }
}
