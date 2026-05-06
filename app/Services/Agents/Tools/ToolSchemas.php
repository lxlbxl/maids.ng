<?php

namespace App\Services\Agents\Tools;

/**
 * ToolSchemas — OpenAI function-calling schema definitions for all Ambassador tools.
 *
 * Centralizes all tool parameter schemas so they can be reused by the agent,
 * the admin dashboard, and any future tool registry.
 */
class ToolSchemas
{
    /**
     * resolve_identity — Look up a user by phone or email across channels.
     */
    public static function resolveIdentity(): array
    {
        return [
            'name' => 'resolve_identity',
            'description' => 'Look up a user by phone number or email address. Returns user ID, name, tier, and verification status. Use this when the user provides contact info or you need to check if they are a registered member.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'phone' => [
                        'type' => 'string',
                        'description' => 'Phone number in international format (e.g., +2348012345678)',
                    ],
                    'email' => [
                        'type' => 'string',
                        'description' => 'Email address',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    /**
     * send_otp — Send a one-time password to a phone number.
     */
    public static function sendOtp(): array
    {
        return [
            'name' => 'send_otp',
            'description' => 'Send a 6-digit one-time password (OTP) to a phone number for identity verification. Use this when a user wants to verify their phone or log in via OTP.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'phone' => [
                        'type' => 'string',
                        'description' => 'Phone number in international format (e.g., +2348012345678)',
                    ],
                ],
                'required' => ['phone'],
            ],
        ];
    }

    /**
     * verify_otp — Verify a one-time password.
     */
    public static function verifyOtp(): array
    {
        return [
            'name' => 'verify_otp',
            'description' => 'Verify a 6-digit OTP sent to a phone number. Returns success status and user info if verified. Use this after sending an OTP and the user provides the code.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'phone' => [
                        'type' => 'string',
                        'description' => 'Phone number the OTP was sent to',
                    ],
                    'otp' => [
                        'type' => 'string',
                        'description' => '6-digit OTP code',
                        'pattern' => '^\d{6}$',
                    ],
                ],
                'required' => ['phone', 'otp'],
            ],
        ];
    }

    /**
     * create_account — Create a new Maids.ng account.
     */
    public static function createAccount(): array
    {
        return [
            'name' => 'create_account',
            'description' => 'Create a new Maids.ng account for an employer or maid. Use this when a guest wants to register. Collect name, phone, email, and role (employer/maid) through conversation first.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'description' => 'Full name of the user',
                    ],
                    'phone' => [
                        'type' => 'string',
                        'description' => 'Phone number in international format',
                    ],
                    'email' => [
                        'type' => 'string',
                        'description' => 'Email address',
                    ],
                    'role' => [
                        'type' => 'string',
                        'enum' => ['employer', 'maid'],
                        'description' => 'User role: employer (hiring) or maid (seeking work)',
                    ],
                    'password' => [
                        'type' => 'string',
                        'description' => 'Password (min 8 characters). If not provided, a temporary password will be generated.',
                    ],
                ],
                'required' => ['name', 'phone', 'role'],
            ],
        ];
    }

    /**
     * find_maid_matches — Find matching maids for an employer.
     */
    public static function findMaidMatches(): array
    {
        return [
            'name' => 'find_maid_matches',
            'description' => 'Find the best matching maids for an employer based on their preferences. Use this when an employer describes what kind of help they need (housekeeper, cook, nanny, etc.), their budget, location, and schedule.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'help_type' => [
                        'type' => 'string',
                        'description' => 'Type of help needed: housekeeper, cook, nanny, elderly_care, live_in, driver, or general',
                    ],
                    'schedule' => [
                        'type' => 'string',
                        'description' => 'Work schedule: full_time, part_time, live_in, or occasional',
                    ],
                    'location' => [
                        'type' => 'string',
                        'description' => 'City or area where the maid will work',
                    ],
                    'budget_min' => [
                        'type' => 'integer',
                        'description' => 'Minimum monthly budget in Naira (₦)',
                    ],
                    'budget_max' => [
                        'type' => 'integer',
                        'description' => 'Maximum monthly budget in Naira (₦)',
                    ],
                    'urgency' => [
                        'type' => 'string',
                        'enum' => ['immediate', 'this_week', 'this_month', 'flexible'],
                        'description' => 'How quickly the employer needs a maid',
                    ],
                ],
                'required' => ['help_type', 'location'],
            ],
        ];
    }

    /**
     * get_pricing — Get current pricing and fee information.
     */
    public static function getPricing(): array
    {
        return [
            'name' => 'get_pricing',
            'description' => 'Get current Maids.ng pricing information including matching fees, commission rates, guarantee terms, and salary ranges. Use this when a user asks about costs, fees, or pricing.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query_type' => [
                        'type' => 'string',
                        'enum' => ['matching_fee', 'commission', 'guarantee', 'salary_range', 'all'],
                        'description' => 'What pricing info to retrieve. Use "all" for a full overview.',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    /**
     * check_assignment_status — Check the status of a maid assignment.
     */
    public static function checkAssignmentStatus(): array
    {
        return [
            'name' => 'check_assignment_status',
            'description' => 'Check the current status of a maid assignment for an employer. Returns assignment stage, maid details (if matched), and next steps. Use this when an employer asks about their matching progress or assignment status.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'employer_id' => [
                        'type' => 'integer',
                        'description' => 'The employer\'s user ID',
                    ],
                ],
                'required' => ['employer_id'],
            ],
        ];
    }

    /**
     * get_support_info — Get support and policy information.
     */
    public static function getSupportInfo(): array
    {
        return [
            'name' => 'get_support_info',
            'description' => 'Get information about Maids.ng policies, guarantees, procedures, and support options. Use this when a user asks about how things work, refund policies, verification process, or other platform rules.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'topic' => [
                        'type' => 'string',
                        'enum' => ['guarantee', 'verification', 'refund', 'matching_process', 'wallet', 'withdrawal', 'general'],
                        'description' => 'The support topic to get information about',
                    ],
                ],
                'required' => ['topic'],
            ],
        ];
    }

    /**
     * escalate_to_human — Escalate a conversation to a human agent.
     */
    public static function escalateToHuman(): array
    {
        return [
            'name' => 'escalate_to_human',
            'description' => 'Escalate the current conversation to a human support agent. Use this when the user requests a human, when the issue is complex, involves refunds/disputes, or when the AI cannot resolve the issue.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'reason' => [
                        'type' => 'string',
                        'description' => 'Reason for escalation (e.g., "user_requested", "refund_request", "dispute", "complex_issue")',
                    ],
                    'priority' => [
                        'type' => 'string',
                        'enum' => ['low', 'normal', 'high', 'urgent'],
                        'description' => 'Escalation priority level',
                    ],
                    'summary' => [
                        'type' => 'string',
                        'description' => 'Brief summary of the conversation and issue',
                    ],
                ],
                'required' => ['reason', 'summary'],
            ],
        ];
    }

    /**
     * Get all tool schemas as an array (for OpenAI API).
     */
    public static function all(): array
    {
        return [
            self::resolveIdentity(),
            self::sendOtp(),
            self::verifyOtp(),
            self::createAccount(),
            self::findMaidMatches(),
            self::getPricing(),
            self::checkAssignmentStatus(),
            self::getSupportInfo(),
            self::escalateToHuman(),
        ];
    }
}