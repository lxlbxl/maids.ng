<?php
/**
 * QoreID Verification Diagnostic Script
 * Tests the entire verification pipeline step by step.
 * Run: php test-qoreid.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

echo "\n" . str_repeat('=', 60) . "\n";
echo "  QoreID VERIFICATION DIAGNOSTIC\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 60) . "\n\n";

// ── Step 1: Check Database Settings ────────────────────────
echo "╔══ STEP 1: Credential Sources ══╗\n\n";

$dbClientId = Setting::get('qoreid_client_id', '');
$dbClientSecret = Setting::get('qoreid_client_secret', '');
$dbBaseUrl = Setting::get('qoreid_base_url', '');

$envClientId = config('services.qoreid.client_id', '');
$envClientSecret = config('services.qoreid.client_secret', '');
$envBaseUrl = config('services.qoreid.base_url', '');

echo "  Database Settings:\n";
echo "    qoreid_client_id:     " . (empty($dbClientId) ? "❌ EMPTY" : "✅ Set (" . strlen($dbClientId) . " chars: " . substr($dbClientId, 0, 8) . "...)") . "\n";
echo "    qoreid_client_secret: " . (empty($dbClientSecret) ? "❌ EMPTY" : "✅ Set (" . strlen($dbClientSecret) . " chars)") . "\n";
echo "    qoreid_base_url:      " . (empty($dbBaseUrl) ? "⚪ Not set (will use default)" : "✅ " . $dbBaseUrl) . "\n";
echo "\n";

echo "  .env / Config Fallback:\n";
echo "    QOREID_CLIENT_ID:     " . (empty($envClientId) ? "❌ EMPTY" : "✅ Set (" . strlen($envClientId) . " chars: " . substr($envClientId, 0, 8) . "...)") . "\n";
echo "    QOREID_CLIENT_SECRET: " . (empty($envClientSecret) ? "❌ EMPTY" : "✅ Set (" . strlen($envClientSecret) . " chars)") . "\n";
echo "    QOREID_BASE_URL:      " . (empty($envBaseUrl) ? "⚪ Not set (will use default)" : "✅ " . $envBaseUrl) . "\n";
echo "\n";

// Resolved values (same logic as QoreIDService constructor)
$clientId = Setting::get('qoreid_client_id', config('services.qoreid.client_id', ''));
$clientSecret = Setting::get('qoreid_client_secret', config('services.qoreid.client_secret', ''));
$rawBaseUrl = Setting::get('qoreid_base_url', config('services.qoreid.base_url', 'https://api.qoreid.com/v1'));

echo "  Resolved Values (what QoreIDService uses):\n";
echo "    Client ID:     " . (empty($clientId) ? "❌ EMPTY — WILL FAIL!" : "✅ " . substr($clientId, 0, 12) . "...") . "\n";
echo "    Client Secret: " . (empty($clientSecret) ? "❌ EMPTY — WILL FAIL!" : "✅ " . strlen($clientSecret) . " chars") . "\n";
echo "    Base URL:      " . $rawBaseUrl . "\n";

if (empty($clientId) || empty($clientSecret)) {
    echo "\n  ⛔ CRITICAL: Credentials are missing!\n";
    echo "     Fix: Go to Admin > Settings and add qoreid_client_id and qoreid_client_secret\n";
    echo "     Or:  Add QOREID_CLIENT_ID and QOREID_CLIENT_SECRET to your .env file\n\n";
    exit(1);
}

// ── Step 2: Check Cache State ──────────────────────────────
echo "\n╔══ STEP 2: Cache State ══╗\n\n";

$cachedToken = Cache::get('qoreid_access_token');
echo "  Cached Access Token: " . (empty($cachedToken) ? "⚪ None (will fetch new)" : "✅ Present (" . strlen($cachedToken) . " chars)") . "\n";

$cachedHealth = Cache::get('qoreid_health_check');
echo "  Cached Health Check: " . (empty($cachedHealth) ? "⚪ None" : "✅ Present — healthy: " . ($cachedHealth['healthy'] ?? 'unknown')) . "\n";

// Clear stale cache for fresh test
Cache::forget('qoreid_access_token');
Cache::forget('qoreid_health_check');
echo "\n  🧹 Cleared cached token and health check for fresh test.\n";

// ── Step 3: Token Acquisition ──────────────────────────────
echo "\n╔══ STEP 3: Token Acquisition ══╗\n\n";

try {
    $client = new GuzzleHttp\Client([
        'timeout' => 30,
        'connect_timeout' => 10,
        'verify' => false,
    ]);

    echo "  Requesting token from: https://auth.qoreid.com/auth/realms/qoreid/protocol/openid-connect/token\n";
    echo "  Grant type: client_credentials\n";
    echo "  Client ID: " . substr($clientId, 0, 12) . "...\n\n";

    $response = $client->post('https://auth.qoreid.com/auth/realms/qoreid/protocol/openid-connect/token', [
        'form_params' => [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ],
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);

    $statusCode = $response->getStatusCode();
    $tokenData = json_decode($response->getBody()->getContents(), true);

    echo "  HTTP Status: {$statusCode}\n";

    if (!empty($tokenData['access_token'])) {
        $token = $tokenData['access_token'];
        echo "  ✅ Token obtained successfully!\n";
        echo "     Token type: " . ($tokenData['token_type'] ?? 'unknown') . "\n";
        echo "     Expires in: " . ($tokenData['expires_in'] ?? 'unknown') . " seconds\n";
        echo "     Token length: " . strlen($token) . " chars\n";
    } else {
        echo "  ❌ Token response missing access_token!\n";
        echo "     Response keys: " . implode(', ', array_keys($tokenData ?? [])) . "\n";
        echo "     Full response: " . json_encode($tokenData, JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }
} catch (\GuzzleHttp\Exception\GuzzleException $e) {
    $errCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
    $errBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
    echo "  ❌ Token request FAILED!\n";
    echo "     HTTP Status: {$errCode}\n";
    echo "     Error: " . $e->getMessage() . "\n";
    if ($errBody) echo "     Response: {$errBody}\n";
    exit(1);
}

// ── Step 4: NIN Premium API Test ───────────────────────────
echo "\n╔══ STEP 4: QoreID NIN Premium API Test ══╗\n\n";

// Compute base URL
$baseUrl = rtrim($rawBaseUrl, '/') . '/v1';
if (str_ends_with(rtrim($rawBaseUrl, '/'), '/v1')) {
    $baseUrl = rtrim($rawBaseUrl, '/');
}

// Use a test NIN (from the n8n screenshot: 80437275370)
$testNin = '00000000000';
$endpoint = "{$baseUrl}/ng/identities/nin-premium/{$testNin}";

echo "  Endpoint: {$endpoint}\n";
echo "  Method: POST\n";
echo "  Body: {\"firstname\": \"TEST\", \"lastname\": \"USER\"}\n\n";

try {
    $response = $client->post($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'json' => [
            'firstname' => 'TEST',
            'lastname' => 'USER',
        ],
    ]);

    $statusCode = $response->getStatusCode();
    $responseData = json_decode($response->getBody()->getContents(), true);

    echo "  HTTP Status: {$statusCode}\n";
    echo "  ✅ API call succeeded!\n";
    echo "     Response ID: " . ($responseData['id'] ?? 'N/A') . "\n";

    if (!empty($responseData['status'])) {
        echo "     Status: " . json_encode($responseData['status']) . "\n";
    }
    if (!empty($responseData['nin'])) {
        echo "     NIN data present: ✅\n";
        echo "     Name from API: " . ($responseData['nin']['firstname'] ?? '?') . " " . ($responseData['nin']['lastname'] ?? '?') . "\n";
    }

} catch (\GuzzleHttp\Exception\GuzzleException $e) {
    $errCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
    $errBody = '';
    if ($e->hasResponse()) {
        $errBody = $e->getResponse()->getBody()->getContents();
    }

    echo "  HTTP Status: {$errCode}\n";

    // Expected errors for a test NIN
    if ($errCode === 404) {
        echo "  ✅ API reachable! Got 404 for dummy NIN (expected behavior).\n";
        echo "     The NIN Premium product IS available on your account.\n";
    } elseif ($errCode === 402) {
        echo "  ⚠️  API reachable, but INSUFFICIENT CREDITS (402).\n";
        echo "     Top up your QoreID wallet to process verifications.\n";
    } elseif ($errCode === 401) {
        echo "  ❌ UNAUTHORIZED (401) — Token may be invalid or expired.\n";
    } elseif ($errCode === 403) {
        echo "  ❌ FORBIDDEN (403) — NIN Premium product not enabled for this account.\n";
    } else {
        echo "  ❌ Unexpected error!\n";
        echo "     Error: " . $e->getMessage() . "\n";
    }

    if ($errBody) {
        $parsed = json_decode($errBody, true);
        if ($parsed) {
            echo "     API Message: " . ($parsed['message'] ?? json_encode($parsed)) . "\n";
        }
    }
}

// ── Step 5: QoreIDService Integration Test ─────────────────
echo "\n╔══ STEP 5: QoreIDService Integration Test ══╗\n\n";

try {
    $service = new \App\Services\QoreIDService();
    $health = $service->healthCheck();

    echo "  Health Check Result:\n";
    echo "    Healthy:           " . ($health['healthy'] ? '✅ Yes' : '❌ No') . "\n";
    echo "    Product Available: " . (is_null($health['product_available']) ? '❓ Unknown' : ($health['product_available'] ? '✅ Yes' : '❌ No')) . "\n";
    echo "    Status Code:       " . ($health['status_code'] ?? 'N/A') . "\n";
    if (!empty($health['error'])) {
        echo "    Error:             " . $health['error'] . "\n";
    }
} catch (\Exception $e) {
    echo "  ❌ QoreIDService instantiation failed: " . $e->getMessage() . "\n";
}

// ── Step 6: GatekeeperAgent Test ───────────────────────────
echo "\n╔══ STEP 6: GatekeeperAgent Standalone Test ══╗\n\n";

try {
    $gatekeeper = new \App\Services\Agents\GatekeeperAgent();
    echo "  ✅ GatekeeperAgent instantiated successfully.\n";
    echo "  (Skipping live NIN verification to avoid API credit spend)\n";
} catch (\Exception $e) {
    echo "  ❌ GatekeeperAgent instantiation failed: " . $e->getMessage() . "\n";
}

// ── Step 7: ProcessStandaloneVerification Job Test ─────────
echo "\n╔══ STEP 7: Job Class Validation ══╗\n\n";

try {
    $reflection = new ReflectionClass(\App\Jobs\ProcessStandaloneVerification::class);
    $backoffProp = $reflection->getProperty('backoff');
    $defaultValue = $backoffProp->getDefaultValue();
    $type = $backoffProp->getType();

    echo "  ProcessStandaloneVerification class:\n";
    echo "    \$backoff type:    " . ($type ? $type->getName() : 'untyped') . "\n";
    echo "    \$backoff default: " . json_encode($defaultValue) . "\n";

    if ($type && $type->getName() === 'int' && is_array($defaultValue)) {
        echo "    ❌ FATAL: \$backoff is typed 'int' but default is an array! This will crash!\n";
    } else {
        echo "    ✅ \$backoff property is correctly typed.\n";
    }

    // Try instantiating
    $job = new \App\Jobs\ProcessStandaloneVerification(99999);
    echo "    ✅ Job class instantiated successfully (no TypeError).\n";
} catch (\TypeError $e) {
    echo "    ❌ FATAL TypeError: " . $e->getMessage() . "\n";
    echo "       This means EVERY standalone verification job crashes on dispatch!\n";
} catch (\Exception $e) {
    echo "    ❌ Error: " . $e->getMessage() . "\n";
}

// ── Step 8: Queue Configuration ────────────────────────────
echo "\n╔══ STEP 8: Queue Configuration ══╗\n\n";

$queueDriver = config('queue.default', 'sync');
echo "  Queue Driver: {$queueDriver}\n";

if ($queueDriver === 'sync') {
    echo "  ℹ️  Jobs run synchronously (no queue worker needed).\n";
    echo "     Verification happens immediately after payment.\n";
} elseif ($queueDriver === 'database') {
    echo "  ℹ️  Jobs use database queue. Ensure `php artisan queue:work` is running.\n";
} else {
    echo "  ℹ️  Jobs use '{$queueDriver}' driver.\n";
}

// ── Summary ────────────────────────────────────────────────
echo "\n" . str_repeat('=', 60) . "\n";
echo "  DIAGNOSTIC COMPLETE\n";
echo str_repeat('=', 60) . "\n\n";
