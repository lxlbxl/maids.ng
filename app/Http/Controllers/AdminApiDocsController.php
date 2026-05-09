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
                            'password' => 'required|string|confirmed',
                            'role' => 'required|in:employer,maid'
                        ],
                        'curl' => "curl -X POST {$baseUrl}/api/v1/auth/register \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"name\": \"John Doe\", \"email\": \"john@example.com\", \"password\": \"secret123\", \"password_confirmation\": \"secret123\", \"role\": \"employer\"}'"
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
                        'auth' => true,
                        'params' => [
                            'help_type' => 'required|string',
                            'location' => 'required|string',
                            'budget' => 'required|numeric'
                        ],
                        'curl' => "curl -X POST {$baseUrl}/api/v1/matching/find \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"help_type\": \"nanny\", \"location\": \"Lagos\", \"budget\": 50000}'"
                    ],
                    [
                        'name' => 'List Maids',
                        'method' => 'GET',
                        'path' => '/api/v1/maids',
                        'description' => 'Browse and search available maids.',
                        'auth' => true,
                        'curl' => "curl -G {$baseUrl}/api/v1/maids \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  -d \"availability_status=available\" \\\n  -d \"per_page=15\""
                    ]
                ]
            ],
            [
                'group' => 'Financials & Wallets',
                'description' => 'Balance inquiries and transaction history.',
                'routes' => [
                    [
                        'name' => 'Wallet Balance',
                        'method' => 'GET',
                        'path' => '/api/v1/wallet',
                        'description' => 'Retrieve current wallet and escrow balances.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/v1/wallet \\\n  -H \"Authorization: Bearer YOUR_TOKEN\""
                    ],
                    [
                        'name' => 'Transaction History',
                        'method' => 'GET',
                        'path' => '/api/v1/wallet/transactions',
                        'description' => 'List all financial transactions.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/v1/wallet/transactions \\\n  -H \"Authorization: Bearer YOUR_TOKEN\""
                    ]
                ]
            ],
            [
                'group' => 'Assignments & Bookings',
                'description' => 'Manage active engagements.',
                'routes' => [
                    [
                        'name' => 'List Assignments',
                        'method' => 'GET',
                        'path' => '/api/v1/assignments',
                        'description' => 'View all current maid-employer assignments.',
                        'auth' => true,
                        'curl' => "curl {$baseUrl}/api/v1/assignments \\\n  -H \"Authorization: Bearer YOUR_TOKEN\""
                    ],
                    [
                        'name' => 'Accept Assignment',
                        'method' => 'POST',
                        'path' => '/api/v1/assignments/{id}/accept',
                        'description' => 'Accept a pending maid assignment.',
                        'auth' => true,
                        'curl' => "curl -X POST {$baseUrl}/api/v1/assignments/42/accept \\\n  -H \"Authorization: Bearer YOUR_TOKEN\""
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
            ]
        ];

        return Inertia::render('Admin/ApiDocs', [
            'endpoints' => $endpoints,
            'baseUrl' => $baseUrl
        ]);
    }
}
