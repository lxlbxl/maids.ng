<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class AdminApiDocsController extends Controller
{
    /**
     * Display the API documentation page.
     */
    public function index()
    {
        $baseUrl = config('app.url');
        
        $endpoints = [
            [
                'group' => 'Authentication',
                'description' => 'User identity and access management.',
                'routes' => [
                    [
                        'name' => 'Login',
                        'method' => 'POST',
                        'path' => '/api/v1/auth/login',
                        'description' => 'Authenticate and receive a Bearer token.',
                        'params' => [
                            'email' => 'required|string|email',
                            'password' => 'required|string',
                            'device_name' => 'optional|string (e.g., iPhone 15)'
                        ],
                        'curl' => "curl -X POST {$baseUrl}/api/v1/auth/login \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"email\": \"admin@maids.ng\", \"password\": \"password\"}'"
                    ],
                    [
                        'name' => 'Register',
                        'method' => 'POST',
                        'path' => '/api/v1/auth/register',
                        'description' => 'Create a new user account.',
                        'params' => [
                            'name' => 'required|string',
                            'email' => 'required|string|email|unique',
                            'phone' => 'required|string|max:20',
                            'password' => 'required|string|confirmed',
                            'role' => 'required|in:employer,maid',
                            'location' => 'optional|string'
                        ],
                        'curl' => "curl -X POST {$baseUrl}/api/v1/auth/register \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"name\": \"John Doe\", \"email\": \"john@example.com\", \"phone\": \"+2348000000000\", \"password\": \"secret123\", \"password_confirmation\": \"secret123\", \"role\": \"employer\"}'"
                    ],
                    [
                        'name' => 'Logout',
                        'method' => 'POST',
                        'path' => '/api/v1/auth/logout',
                        'description' => 'Revoke the current access token.',
                        'auth' => true,
                        'curl' => "curl -X POST {$baseUrl}/api/v1/auth/logout \\\n  -H \"Authorization: Bearer YOUR_TOKEN\""
                    ]
                ]
            ],
            [
                'group' => 'Matching & Profiles',
                'description' => 'Core marketplace matching logic.',
                'routes' => [
                    [
                        'name' => 'Find Matches',
                        'method' => 'POST',
                        'path' => '/api/v1/matching/find',
                        'description' => 'AI-powered matching for employer preferences.',
                        'auth' => false,
                        'params' => [
                            'help_types' => 'required|array',
                            'schedule' => 'required|string',
                            'urgency' => 'required|string',
                            'location' => 'required|string',
                            'budget_max' => 'optional|integer'
                        ],
                        'curl' => "curl -X POST {$baseUrl}/api/v1/matching/find \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"help_types\": [\"nanny\"], \"schedule\": \"full-time\", \"urgency\": \"immediate\", \"location\": \"Lagos, NG\", \"budget_max\": 50000}'"
                    ],
                    [
                        'name' => 'List Maids',
                        'method' => 'GET',
                        'path' => '/api/v1/maids',
                        'description' => 'Browse and search available maids.',
                        'auth' => true,
                        'curl' => "curl -G {$baseUrl}/api/v1/maids \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  -d \"location=Lagos\" \\\n  -d \"per_page=15\""
                    ]
                ]
            ],
            [
                'group' => 'Administrative (Agents Only)',
                'description' => 'High-level platform monitoring.',
                'routes' => [
                    [
                        'name' => 'Platform Overview',
                        'method' => 'GET',
                        'path' => '/api/v1/reports/platform-overview',
                        'description' => 'Get global system health and metrics.',
                        'auth' => true,
                        'role' => 'admin',
                        'curl' => "curl {$baseUrl}/api/v1/reports/platform-overview \\\n  -H \"Authorization: Bearer YOUR_TOKEN\""
                    ]
                ]
            ],
            [
                'group' => 'Agent API — Platform & Health',
                'description' => 'Operational dashboards for external agents. Auth via Bearer mng_sk_{key}.',
                'routes' => [
                    [
                        'name' => 'Platform KPIs',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/metrics/platform',
                        'description' => 'Full platform metrics — registrations, payments, assignments, maids, employers.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/metrics/platform \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'Agent Health',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/metrics/agent-health',
                        'description' => 'Circuit breaker states for all internal agents.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/metrics/agent-health \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'Revenue Summary',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/metrics/revenue',
                        'description' => 'GMV, matching fees, escrow, payouts.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/metrics/revenue \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'Conversion Funnel',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/metrics/funnel',
                        'description' => 'Visitors → registrations → quiz → payment → active.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/metrics/funnel \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ]
                ]
            ],
            [
                'group' => 'Agent API — Users',
                'description' => 'Resolve users and get full context.',
                'routes' => [
                    [
                        'name' => 'User Lookup',
                        'method' => 'POST',
                        'path' => '/api/agent-api/v1/users/lookup',
                        'description' => 'Find user by phone, email, or ID. Primary resolver for all agents.',
                        'auth' => true,
                        'params' => ['phone' => 'optional|string', 'email' => 'optional|email', 'user_id' => 'optional|integer'],
                        'curl' => "curl -X POST {$baseUrl}/api/agent-api/v1/users/lookup \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"phone\":\"+2348012345678\"}'"
                    ],
                    [
                        'name' => 'User Summary',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/users/{id}/summary',
                        'description' => 'Full context: onboarding status, preferences, recent messages, lead score.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/users/1/summary \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'Create User',
                        'method' => 'POST',
                        'path' => '/api/agent-api/v1/users',
                        'description' => 'Create employer or maid account.',
                        'auth' => true,
                        'params' => ['name' => 'required|string', 'phone' => 'required|string', 'role' => 'required|in:employer,maid', 'email' => 'optional|email'],
                        'curl' => "curl -X POST {$baseUrl}/api/agent-api/v1/users \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"name\":\"Jane Doe\",\"phone\":\"08012345678\",\"role\":\"employer\"}'"
                    ],
                    [
                        'name' => 'Update User',
                        'method' => 'PATCH',
                        'path' => '/api/agent-api/v1/users/{id}',
                        'description' => 'Update profile fields (name, phone, email, status).',
                        'auth' => true,
                        'curl' => "curl -X PATCH {$baseUrl}/api/agent-api/v1/users/18 \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"status\":\"active\"}'"
                    ],
                    [
                        'name' => 'Conversation History',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/users/{id}/conversation-history',
                        'description' => 'Last 50 messages across all channels for a user.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/users/1/conversation-history \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'Scan Inactive Users',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/users/scan/inactive',
                        'description' => 'Employers with no login in 30+ days.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/users/scan/inactive \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'Scan Incomplete Maids',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/users/scan/incomplete-maids',
                        'description' => 'Maids with profile completeness < 80%.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/users/scan/incomplete-maids \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ]
                ]
            ],
            [
                'group' => 'Agent API — Communications',
                'description' => 'Send messages, initiate calls, manage threads.',
                'routes' => [
                    [
                        'name' => 'Send Message',
                        'method' => 'POST',
                        'path' => '/api/agent-api/v1/messages/send',
                        'description' => 'Send WhatsApp, SMS or email to a user.',
                        'auth' => true,
                        'params' => ['user_id' => 'required|integer', 'channel' => 'required|in:whatsapp,sms,email', 'message' => 'required|string', 'phone' => 'optional|string', 'email' => 'optional|email'],
                        'curl' => "curl -X POST {$baseUrl}/api/agent-api/v1/messages/send \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"user_id\":2,\"channel\":\"whatsapp\",\"phone\":\"+2348012345678\",\"message\":\"Hello from Maids.ng!\"}'"
                    ],
                    [
                        'name' => 'Send SMS',
                        'method' => 'POST',
                        'path' => '/api/agent-api/v1/messages/sms',
                        'description' => 'Send raw SMS to a phone number.',
                        'auth' => true,
                        'params' => ['phone' => 'required|string', 'message' => 'required|string'],
                        'curl' => "curl -X POST {$baseUrl}/api/agent-api/v1/messages/sms \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"phone\":\"+2348012345678\",\"message\":\"Your verification code is 123456\"}'"
                    ],
                    [
                        'name' => 'Initiate Call',
                        'method' => 'POST',
                        'path' => '/api/agent-api/v1/messages/call',
                        'description' => 'Initiate VAPI AI voice call.',
                        'auth' => true,
                        'params' => ['phone' => 'required|string', 'call_type' => 'required|string', 'context' => 'optional|string'],
                        'curl' => "curl -X POST {$baseUrl}/api/agent-api/v1/messages/call \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"phone\":\"+2348012345678\",\"call_type\":\"onboarding_welcome\",\"context\":\"New employer\"}'"
                    ],
                    [
                        'name' => 'Ambassador AI Chat',
                        'method' => 'POST',
                        'path' => '/api/agent-api/v1/messages/ambassador',
                        'description' => 'Route through full Ambassador AI pipeline.',
                        'auth' => true,
                        'curl' => "curl -X POST {$baseUrl}/api/agent-api/v1/messages/ambassador \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"channel\":\"whatsapp\",\"from_phone\":\"+2348012345678\",\"content\":\"How much does a housekeeper cost?\"}'"
                    ],
                ]
            ],
            [
                'group' => 'Agent API — Conversations',
                'description' => 'n8n WhatsApp integration — log messages, get history, resolve identities.',
                'routes' => [
                    [
                        'name' => 'Log Message',
                        'method' => 'POST',
                        'path' => '/api/agent-api/v1/conversations/message',
                        'description' => 'Resolve identity, create conversation, store message. Used by n8n for WhatsApp.',
                        'auth' => true,
                        'params' => ['channel' => 'required|in:whatsapp,sms,email,web,phone,vapi', 'from_phone' => 'optional|string', 'content' => 'required|string', 'role' => 'optional|in:user,assistant,system'],
                        'curl' => "curl -X POST {$baseUrl}/api/agent-api/v1/conversations/message \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"channel\":\"whatsapp\",\"from_phone\":\"+2348012345678\",\"content\":\"I need a housekeeper\",\"role\":\"user\"}'"
                    ],
                    [
                        'name' => 'List Conversations',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/conversations',
                        'description' => 'Filter by channel, user_id, status.',
                        'auth' => true,
                        'curl' => "curl \"{$baseUrl}/api/agent-api/v1/conversations?channel=whatsapp&status=open\" \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'Get Conversation',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/conversations/{id}',
                        'description' => 'Conversation detail with identity.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/conversations/12 \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'Message History',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/conversations/{id}/messages',
                        'description' => 'Last 50 messages for AI context building.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/conversations/12/messages \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'Identity Lookup',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/agent/identity/lookup',
                        'description' => 'Find channel identity by phone/email.',
                        'auth' => true,
                        'curl' => "curl \"{$baseUrl}/api/agent-api/v1/agent/identity/lookup?channel=whatsapp&phone=+2348012345678\" \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ]
                ]
            ],
            [
                'group' => 'Agent API — Operations',
                'description' => 'Onboarding, fulfillment, sales, CS — the full agent workflow.',
                'routes' => [
                    [
                        'name' => 'Onboarding Scans',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/onboarding/scan/{type}',
                        'description' => '6 scan types: needs-welcome-call, quiz-abandoned, awaiting-payment, maid-profile-incomplete, nin-pending, abandoned.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/onboarding/scan/quiz-abandoned \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'Fulfillment — Open Case',
                        'method' => 'POST',
                        'path' => '/api/agent-api/v1/fulfillment/open',
                        'description' => 'Open fulfillment case after payment. Drives: payment_confirmed → salary → start date → day_one → active.',
                        'auth' => true,
                        'params' => ['employer_id' => 'required|integer', 'maid_id' => 'optional|integer', 'preference_id' => 'optional|integer'],
                        'curl' => "curl -X POST {$baseUrl}/api/agent-api/v1/fulfillment/open \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"employer_id\":2,\"maid_id\":3,\"preference_id\":1}'"
                    ],
                    [
                        'name' => 'Fulfillment Scans',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/fulfillment/scan/{type}',
                        'description' => '5 scan types: all-active, stalled, awaiting-first-day, day-one-not-confirmed, ready-to-activate.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/fulfillment/scan/stalled \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'Sales Scans',
                        'method' => 'GET',
                        'path' => '/api/agent-api/v1/sales/scan/{type}',
                        'description' => '6 scan types: hot-leads, warm-leads, payment-pending, winback-recent, winback-lapsed, upsell-candidates.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/agent-api/v1/sales/scan/hot-leads \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\""
                    ],
                    [
                        'name' => 'CS Ticket Management',
                        'method' => 'POST',
                        'path' => '/api/agent-api/v1/cs/tickets',
                        'description' => 'Open support ticket. Full CRUD: GET /tickets, PATCH /tickets/{id}, POST /tickets/{id}/resolve, POST /tickets/{id}/escalate.',
                        'auth' => true,
                        'params' => ['cs_case_id' => 'required|integer', 'user_id' => 'required|integer', 'type' => 'required|string', 'description' => 'required|string', 'priority' => 'required|in:low,medium,high,critical'],
                        'curl' => "curl -X POST {$baseUrl}/api/agent-api/v1/cs/tickets \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"cs_case_id\":7,\"user_id\":2,\"role\":\"employer\",\"type\":\"salary_delay\",\"description\":\"Salary not paid\",\"priority\":\"high\"}'"
                    ],
                    [
                        'name' => 'Agent Notes',
                        'method' => 'POST',
                        'path' => '/api/agent-api/v1/notes',
                        'description' => 'Universal audit trail. Every agent action should be logged here.',
                        'auth' => true,
                        'params' => ['entity_type' => 'required|string', 'entity_id' => 'required|integer', 'note' => 'required|string', 'action_taken' => 'optional|string', 'outcome' => 'optional|string'],
                        'curl' => "curl -X POST {$baseUrl}/api/agent-api/v1/notes \\\n  -H \"Authorization: Bearer YOUR_AGENT_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"entity_type\":\"fulfillment_case\",\"entity_id\":7,\"note\":\"Called employer — confirmed salary\",\"action_taken\":\"call_placed\",\"outcome\":\"success\"}'"
                    ]
                ]
            ],
        ];

        return Inertia::render('Admin/ApiDocs', [
            'endpoints' => $endpoints,
            'baseUrl' => $baseUrl
        ]);
    }
}
