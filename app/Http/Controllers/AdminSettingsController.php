<?php
namespace App\Http\Controllers;
use App\Models\Setting;
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
                // CRITICAL: Decrypt encrypted values before sending to the frontend.
                // Without this, the UI receives ciphertext and re-encrypts it on save,
                // causing double-encryption that corrupts API keys.
                $value = $setting->value;
                if ($setting->is_encrypted && $value) {
                    try {
                        $value = \Illuminate\Support\Facades\Crypt::decryptString($value);
                    } catch (\Exception $e) {
                        // If decryption fails (already plain text or corrupt), use raw value
                        $value = $setting->value;
                    }
                }

                return [
                    'key' => $setting->key,
                    'value' => $value,
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
            'settings'   => $settings,
            'aiManifest' => $aiService->getProviderManifest(),
            'smsProvider' => $smsProvider,
            'mcpServers' => \App\Models\McpServer::orderBy('name')->get(),
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
            'contact_phone' => 'general',
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
            // Agent Channel Settings (Meta, WhatsApp, Email Polling)
            'meta_page_access_token' => 'agents',
            'meta_webhook_verify_token' => 'agents',
            'meta_app_secret' => 'agents',
            'meta_default_reply' => 'agents',
            'whatsapp_from_number' => 'agents',
            'whatsapp_default_reply' => 'agents',
            'email_imap_host' => 'agents',
            'email_imap_port' => 'agents',
            'email_imap_username' => 'agents',
            'email_imap_password' => 'agents',
            'email_imap_folder' => 'agents',
            'email_poll_interval_seconds' => 'agents',
            'email_default_reply' => 'agents',
            // Script & Pixel Injection — Public Frontend
            'script_google_head_frontend'  => 'scripts',
            'script_google_body_frontend'  => 'scripts',
            'script_google_footer_frontend' => 'scripts',
            'script_meta_head_frontend'    => 'scripts',
            'script_meta_body_frontend'    => 'scripts',
            'script_meta_footer_frontend'  => 'scripts',
            'script_custom_head_frontend'  => 'scripts',
            'script_custom_body_frontend'  => 'scripts',
            'script_custom_footer_frontend' => 'scripts',
            // Script & Pixel Injection — Member Area (Employer / Maid)
            'script_google_head_member'    => 'scripts',
            'script_google_body_member'    => 'scripts',
            'script_google_footer_member'  => 'scripts',
            'script_meta_head_member'      => 'scripts',
            'script_meta_body_member'      => 'scripts',
            'script_meta_footer_member'    => 'scripts',
            'script_custom_head_member'    => 'scripts',
            'script_custom_body_member'    => 'scripts',
            'script_custom_footer_member'  => 'scripts',
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
            // Verify database connectivity before attempting writes
            \Illuminate\Support\Facades\DB::connection()->getPdo();

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

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Settings update failed — database error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'sql' => $e->getSql ?? null,
            ]);

            // Surface the actual error so the admin can diagnose without SSH access
            $dbError = $e->getMessage();

            // Extract the meaningful part from the PDO exception message
            if (preg_match('/SQLSTATE\[.*?\].*?:\s*(.+)/s', $dbError, $matches)) {
                $dbError = trim($matches[1]);
            }

            if (str_contains($e->getMessage(), 'Access denied')) {
                $dbError = 'Database access denied — please verify your DB credentials in .env and clear config cache at /admin/clear-cache';
            }

            return back()->with('error', 'Database error: ' . $dbError);

        } catch (\Exception $e) {
            Log::error('Settings update failed', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'An error occurred while saving settings: ' . $e->getMessage());
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

    // ──────────────────────────────────────────────────────────────
    //  Connection Test Endpoints
    // ──────────────────────────────────────────────────────────────

    /**
     * Test QoreID connection — uses the actual QoreIDService.
     */
    public function testQoreid(Request $request): JsonResponse
    {
        $clientId = Setting::get('qoreid_client_id', config('services.qoreid.client_id', ''));
        $clientSecret = Setting::get('qoreid_client_secret', config('services.qoreid.client_secret', ''));

        if (!$clientId || !$clientSecret) {
            return response()->json([
                'success' => false,
                'message' => 'QoreID Client ID and Client Secret are both required.',
            ], 400);
        }

        try {
            $qoreid = new \App\Services\QoreIDService();
            $result = $qoreid->healthCheck();

            if ($result['healthy'] && $result['product_available']) {
                return response()->json([
                    'success' => true,
                    'message' => 'QoreID connected successfully. NIN Premium product is available.',
                    'status_code' => $result['status_code'],
                ]);
            }

            if ($result['healthy'] && !$result['product_available']) {
                return response()->json([
                    'success' => true,
                    'message' => 'QoreID is reachable but NIN Premium is not available for this account. Check your product subscription.',
                    'status_code' => $result['status_code'],
                    'error' => $result['error'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'QoreID connection failed.',
                'status_code' => $result['status_code'],
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Paystack connection — verifies a fake reference (expected to fail gracefully).
     */
    public function testPaystack(Request $request): JsonResponse
    {
        $secretKey = Setting::get('paystack_secret_key', config('services.paystack.secret_key', ''));
        $baseUrl = Setting::get('paystack_base_url', config('services.paystack.base_url', 'https://api.paystack.co'));

        if (!$secretKey) {
            return response()->json(['success' => false, 'message' => 'Paystack secret key not configured.'], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withToken(trim($secretKey))
                ->get("{$baseUrl}/transaction/verify/test_reference_invalid_12345");

            $status = $response->status();
            $body = $response->json();

            // Paystack returns 200 with status:false for invalid refs — that means auth works
            if ($status === 200 && isset($body['status']) && $body['status'] === false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Paystack connected successfully. API is responding.',
                    'status_code' => $status,
                ]);
            }

            // 401 = auth failed
            if ($status === 401) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paystack authentication failed. Check your secret key.',
                    'status_code' => $status,
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => "Paystack connected (status: {$status}).",
                'status_code' => $status,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Flutterwave connection.
     */
    public function testFlutterwave(Request $request): JsonResponse
    {
        $secretKey = Setting::get('flutterwave_secret_key', config('services.flutterwave.secret_key', ''));
        $baseUrl = Setting::get('flutterwave_base_url', config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3'));

        if (!$secretKey) {
            return response()->json(['success' => false, 'message' => 'Flutterwave secret key not configured.'], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withToken(trim($secretKey))
                ->get("{$baseUrl}/transactions/verify_by_reference", [
                    'tx_ref' => 'test_invalid_ref_12345',
                ]);

            $status = $response->status();
            $body = $response->json();

            // Flutterwave returns 200 with status for invalid refs — auth works
            if ($status === 200) {
                return response()->json([
                    'success' => true,
                    'message' => 'Flutterwave connected successfully. API is responding.',
                    'status_code' => $status,
                ]);
            }

            if ($status === 401 || $status === 403) {
                return response()->json([
                    'success' => false,
                    'message' => 'Flutterwave authentication failed. Check your secret key.',
                    'status_code' => $status,
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => "Flutterwave connected (status: {$status}).",
                'status_code' => $status,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test OpenAI connection — lists models (lightweight, no cost).
     */
    public function testOpenai(Request $request): JsonResponse
    {
        $apiKey = Setting::get('openai_key', config('services.openai.key', ''));

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'OpenAI API key not configured.'], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withToken($apiKey)
                ->get('https://api.openai.com/v1/models');

            $status = $response->status();

            if ($status === 200) {
                $models = $response->json('data', []);
                $count = count($models);
                return response()->json([
                    'success' => true,
                    'message' => "OpenAI connected successfully. Found {$count} models.",
                    'status_code' => $status,
                    'models_count' => $count,
                ]);
            }

            if ($status === 401) {
                return response()->json([
                    'success' => false,
                    'message' => 'OpenAI authentication failed. Check your API key.',
                    'status_code' => $status,
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => "OpenAI returned status: {$status}",
                'status_code' => $status,
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test OpenRouter connection — lists models (public endpoint, no auth needed for listing).
     */
    public function testOpenrouter(Request $request): JsonResponse
    {
        $apiKey = Setting::get('openrouter_key', config('services.openrouter.key', ''));

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'OpenRouter API key not configured.'], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url', 'https://maids.ng'),
                    'X-Title' => 'Maids.ng Mission Control',
                ])
                ->get('https://openrouter.ai/api/v1/models');

            $status = $response->status();

            if ($status === 200) {
                $models = $response->json('data', []);
                $count = count($models);
                return response()->json([
                    'success' => true,
                    'message' => "OpenRouter connected successfully. Found {$count} models.",
                    'status_code' => $status,
                    'models_count' => $count,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "OpenRouter returned status: {$status}",
                'status_code' => $status,
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Termii connection — fetches balance.
     */
    public function testTermii(Request $request): JsonResponse
    {
        $provider = new \App\Services\Sms\TermiiProvider();
        $apiKey = Setting::get('termii_api_key', config('services.termii.api_key', ''));

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'Termii API key not configured.'], 400);
        }

        try {
            // If phone provided, send a test SMS
            if ($request->has('phone') && !empty($request->phone)) {
                $result = $provider->send(
                    $request->phone,
                    $request->input('message', 'Test SMS from Maids.ng admin panel. If you received this, SMS is working!')
                );

                return response()->json([
                    'success' => $result['success'],
                    'message' => $result['success'] ? 'SMS sent successfully!' : ($result['error'] ?? 'Failed to send'),
                    'data' => $result,
                ]);
            }

            // Otherwise just check balance
            $result = $provider->getBalance();

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Termii connected. Balance: ' . ($result['balance'] ?? 'N/A'),
                    'balance' => $result['balance'] ?? null,
                    'currency' => $result['currency'] ?? 'NGN',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Termii connection failed.',
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Twilio connection — fetches balance.
     */
    public function testTwilio(Request $request): JsonResponse
    {
        $sid = Setting::get('twilio_sid', config('services.twilio.sid', ''));
        $token = Setting::get('twilio_token', config('services.twilio.token', ''));

        if (!$sid || !$token) {
            return response()->json(['success' => false, 'message' => 'Twilio credentials not configured.'], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withBasicAuth($sid, $token)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Balance.json");

            $status = $response->status();
            $body = $response->json();

            if ($status === 200 && isset($body['balance'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Twilio connected successfully. Balance: ' . ($body['balance'] ?? 'N/A'),
                    'status_code' => $status,
                    'balance' => $body['balance'] ?? null,
                    'currency' => $body['currency'] ?? 'USD',
                ]);
            }

            if ($status === 401 || $status === 403) {
                return response()->json([
                    'success' => false,
                    'message' => 'Twilio authentication failed. Check your SID and Token.',
                    'status_code' => $status,
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => "Twilio returned status: {$status}",
                'status_code' => $status,
                'response' => $body,
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Africa's Talking connection — fetches user/balance.
     */
    public function testAfricasTalking(Request $request): JsonResponse
    {
        $username = Setting::get('africastalking_username', config('services.africastalking.username', ''));
        $apiKey = Setting::get('africastalking_api_key', config('services.africastalking.api_key', ''));

        if (!$username || !$apiKey) {
            return response()->json(['success' => false, 'message' => "Africa's Talking credentials not configured."], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withHeaders(['apiKey' => $apiKey, 'Accept' => 'application/json'])
                ->get("https://api.africastalking.com/version1/user?username={$username}");

            $status = $response->status();
            $body = $response->json();

            if ($status === 200 && isset($body['UserData'])) {
                $balance = $body['UserData']['balance'] ?? 'N/A';
                return response()->json([
                    'success' => true,
                    'message' => "Africa's Talking connected successfully. Balance: {$balance}",
                    'status_code' => $status,
                    'balance' => $balance,
                ]);
            }

            if ($status === 401 || $status === 403) {
                return response()->json([
                    'success' => false,
                    'message' => "Africa's Talking authentication failed. Check your username and API key.",
                    'status_code' => $status,
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => "Africa's Talking returned status: {$status}",
                'status_code' => $status,
                'response' => $body,
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Email (SMTP) connection — attempts to connect via mail driver.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $mailer = Setting::get('mail_mailer', config('mail.default', 'smtp'));
        $host = Setting::get('mail_host', config('mail.mailers.smtp.host', ''));
        $port = Setting::get('mail_port', config('mail.mailers.smtp.port', 587));
        $username = Setting::get('mail_username', config('mail.mailers.smtp.username', ''));
        $password = Setting::get('mail_password', config('mail.mailers.smtp.password', ''));

        if (!$host || !$username) {
            return response()->json(['success' => false, 'message' => 'Email settings not configured.'], 400);
        }

        try {
            // Attempt a socket connection to verify SMTP is reachable
            $socket = @fsockopen($host, (int) $port, $errno, $errstr, 10);
            if ($socket) {
                fclose($socket);
                return response()->json([
                    'success' => true,
                    'message' => "Email server connected successfully ({$host}:{$port}).",
                    'host' => $host,
                    'port' => $port,
                    'mailer' => $mailer,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Cannot connect to email server: {$errstr} ({$errno})",
                'host' => $host,
                'port' => $port,
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Meta/WhatsApp connection — fetches page info.
     */
    public function testMeta(Request $request): JsonResponse
    {
        $accessToken = Setting::get('meta_page_access_token', config('services.whatsapp.access_token', ''));

        if (!$accessToken) {
            return response()->json(['success' => false, 'message' => 'Meta page access token not configured.'], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->get("https://graph.facebook.com/v18.0/me", [
                    'access_token' => $accessToken,
                    'fields' => 'id,name,username',
                ]);

            $status = $response->status();
            $body = $response->json();

            if ($status === 200 && isset($body['id'])) {
                return response()->json([
                    'success' => true,
                    'message' => "Meta connected successfully. Page: {$body['name']}",
                    'status_code' => $status,
                    'page_id' => $body['id'] ?? null,
                    'page_name' => $body['name'] ?? null,
                ]);
            }

            if ($status === 400 || $status === 401) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meta authentication failed. Check your page access token.',
                    'status_code' => $status,
                    'error' => $body['error']['message'] ?? 'Unknown error',
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => "Meta returned status: {$status}",
                'status_code' => $status,
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
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

            // If fetching failed (e.g. API key not configured), return the
            // hardcoded fallback models so the Settings page can still render
            // a valid model dropdown — just with a warning message.
            if (isset($result['error'])) {
                $fallbackModels = $aiService->getProviderManifest()[$provider]['models'] ?? [];

                return response()->json([
                    'success' => true,
                    'data' => [
                        'provider' => $provider,
                        'models' => $fallbackModels,
                        'total_available' => count($fallbackModels),
                        'showing' => count($fallbackModels),
                        'has_more' => false,
                        'is_fallback' => true,
                    ],
                    'message' => $result['error'],
                ]);
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
                    'is_fallback' => false,
                ],
                'message' => 'Models retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error("AI model fetch error for {$provider}", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Even on server error, return fallback models so the page doesn't break
            $fallbackModels = (new \App\Services\Ai\AiService())->getProviderManifest()[$provider]['models'] ?? [];

            return response()->json([
                'success' => true,
                'data' => [
                    'provider' => $provider,
                    'models' => $fallbackModels,
                    'total_available' => count($fallbackModels),
                    'showing' => count($fallbackModels),
                    'has_more' => false,
                    'is_fallback' => true,
                ],
                'message' => 'Could not fetch live models. Showing defaults.',
            ]);
        }
    }

    /**
     * Generate a new API token for the current admin.
     */
    public function generateApiToken(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $key = \App\Models\AgentApiKey::generateKey($request->name, 'admin', ['*']);

        return response()->json([
            'success' => true,
            'token' => $key->plain_key,
            'message' => 'New API token generated successfully. Make sure to copy it now, as it won\'t be shown again.'
        ]);
    }

    /**
     * Revoke all API tokens for agent keys.
     */
    public function revokeApiTokens(Request $request): JsonResponse
    {
        \App\Models\AgentApiKey::query()->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'All agent API keys have been revoked.'
        ]);
    }
}
