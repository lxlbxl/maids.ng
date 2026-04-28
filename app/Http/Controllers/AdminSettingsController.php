<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AdminSettingsController extends Controller
{
    public function index()
    {
        $settings = \App\Models\Setting::all()->groupBy('group')->map(function ($group) {
            return $group->map(function ($setting) {
                return [
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'is_encrypted' => $setting->is_encrypted,
                    'group' => $setting->group,
                ];
            })->values()->all();
        })->all();

        $aiService = new \App\Services\Ai\AiService();

        // Resolve active SMS provider info
        $smsProvider = null;
        try {
            $sms = app(\App\Services\Sms\SmsProviderInterface::class);
            $smsProvider = [
                'name' => $sms->name(),
                'balance' => $sms->getBalance(),
            ];
        } catch (\Throwable $e) {
            // Provider not configured — that's fine
        }

        return Inertia::render('Admin/Settings', [
            'settings' => $settings,
            'aiManifest' => $aiService->getProviderManifest(),
            'smsProvider' => $smsProvider,
        ]);
    }

    /**
     * Map setting keys to their appropriate groups.
     */
    private function getSettingGroup(string $key): string
    {
        $groupMap = [
            // AI Settings
            'ai_active_provider' => 'ai',
            'openai_model' => 'ai',
            'openrouter_model' => 'ai',
            'openai_key' => 'ai',
            'openrouter_key' => 'ai',
            'ai_temperature' => 'ai',
            'ai_max_tokens' => 'ai',
            'ai_system_prompt' => 'ai',
            'ai_matching_enabled' => 'ai',
            'ai_matching_max_results' => 'ai',
            'ai_matching_min_score' => 'ai',
            // App Settings
            'platform_name' => 'general',
            'app_url' => 'general',
            'app_timezone' => 'general',
            'app_debug' => 'general',
            'support_email' => 'general',
            'support_phone' => 'general',
            'maintenance_mode' => 'general',
            // Financial Settings
            'service_fee_percentage' => 'finance',
            'matching_fee_amount' => 'finance',
            'guarantee_match_fee' => 'finance',
            'nin_verification_fee' => 'finance',
            'standalone_verification_fee' => 'finance',
            'commission_type' => 'finance',
            'commission_percent' => 'finance',
            'commission_fixed_amount' => 'finance',
            'min_salary' => 'finance',
            'max_salary' => 'finance',
            'min_withdrawal' => 'finance',
            'max_withdrawal' => 'finance',
            'withdrawal_processing_days' => 'finance',
            // Payment Gateway Settings
            'paystack_public_key' => 'payment',
            'paystack_secret_key' => 'payment',
            'paystack_base_url' => 'payment',
            'flutterwave_public_key' => 'payment',
            'flutterwave_secret_key' => 'payment',
            'flutterwave_encryption_key' => 'payment',
            'flutterwave_base_url' => 'payment',
            'default_payment_gateway' => 'payment',
            // Verification Settings
            'qoreid_token' => 'verification',
            'qoreid_base_url' => 'verification',
            'verification_auto_approve' => 'verification',
            // SMS Settings
            'sms_active_provider' => 'sms',
            'termii_api_key' => 'sms',
            'termii_sender_id' => 'sms',
            'termii_url' => 'sms',
            'twilio_sid' => 'sms',
            'twilio_token' => 'sms',
            'twilio_from' => 'sms',
            'africastalking_username' => 'sms',
            'africastalking_api_key' => 'sms',
            'africastalking_from' => 'sms',
            // Email Settings
            'mail_mailer' => 'email',
            'mail_host' => 'email',
            'mail_port' => 'email',
            'mail_username' => 'email',
            'mail_password' => 'email',
            'mail_encryption' => 'email',
            'mail_from_address' => 'email',
            'mail_from_name' => 'email',
            // Notification Settings
            'notification_work_hours_start' => 'notifications',
            'notification_work_hours_end' => 'notifications',
            'notification_max_retries' => 'notifications',
            'notification_retry_delay_minutes' => 'notifications',
            'notification_batch_size' => 'notifications',
            // Salary Settings
            'salary_default_day' => 'salary',
            'salary_reminder_days_before' => 'salary',
            'salary_auto_debit_enabled' => 'salary',
            'salary_escalation_after_days' => 'salary',
            'salary_max_escalation_level' => 'salary',
            // Security Settings
            'deploy_secret' => 'security',
            'api_rate_limit' => 'security',
            'session_lifetime' => 'security',
        ];

        return $groupMap[$key] ?? 'general';
    }

    /**
     * Check if a setting key should be encrypted.
     */
    private function shouldEncrypt(string $key): bool
    {
        $sensitivePatterns = [
            'key',
            'secret',
            'token',
            'password',
            'encryption',
            'sid',
        ];

        $keyLower = strtolower($key);
        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($keyLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'settings' => 'required|array',
        ]);

        try {
            foreach ($data['settings'] as $key => $config) {
                if (!is_array($config) || $config === null) {
                    continue;
                }

                $value = $config['value'] ?? '';
                $group = $this->getSettingGroup($key);
                $isEncrypted = $this->shouldEncrypt($key);

                if ($value === null) {
                    $value = '';
                }

                \App\Models\Setting::set($key, $value, $group, $isEncrypted);
            }

            return back()->with('success', 'System settings updated successfully.');

        } catch (\Exception $e) {
            Log::error('Settings update failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'An error occurred while saving settings. Please check the system logs for details.');
        }
    }

    /**
     * Test the currently active SMS provider.
     */
    public function testSms(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'nullable|string|max:160',
        ]);

        try {
            $sms = app(\App\Services\Sms\SmsProviderInterface::class);
            $result = $sms->send(
                $request->phone,
                $request->input('message', 'Test SMS from Maids.ng admin panel. If you received this, SMS is working!')
            );

            return response()->json([
                'success' => $result['success'],
                'provider' => $sms->name(),
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch AI Models from Provider API (AJAX endpoint).
     */
    public function fetchAiModels(Request $request, string $provider): JsonResponse
    {
        $validProviders = ['openai', 'openrouter'];

        if (!in_array($provider, $validProviders)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid provider. Must be openai or openrouter',
            ], 422);
        }

        try {
            $aiService = new \App\Services\Ai\AiService();
            $result = $aiService->fetchModels($provider);

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                ], 400);
            }

            $models = $result['models'] ?? [];
            $search = $request->get('search', '');

            if ($search) {
                $filtered = [];
                foreach ($models as $id => $name) {
                    if (stripos($id, $search) !== false || stripos($name, $search) !== false) {
                        $filtered[$id] = $name;
                    }
                }
                $models = $filtered;
            }

            $totalAvailable = count($models);

            return response()->json([
                'success' => true,
                'data' => [
                    'provider' => $provider,
                    'models' => $models,
                    'total_available' => $totalAvailable,
                    'showing' => $totalAvailable,
                    'has_more' => false,
                ],
                'message' => 'Models retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error("AI model fetch error for {$provider}", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected server error occurred while fetching models.',
            ], 500);
        }
    }
}
