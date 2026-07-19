<?php

namespace App\Http\Controllers\Api;

use App\Models\AgentApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WacrmMediaController — proxies WhatsApp media downloads for the Paperclip agent.
 *
 * The WACRM media endpoint requires Supabase cookie auth (dashboard session).
 * The Paperclip agent uses API keys which can't access it.
 *
 * This controller reads the WhatsApp config directly from WACRM's Supabase,
 * resolves the Meta CDN URL, downloads the binary, and returns it to the agent.
 */
class WacrmMediaController extends ApiController
{
    private const SUPABASE_URL = 'http://localhost:8100';
    private const SUPABASE_SERVICE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJyb2xlIjoic2VydmljZV9yb2xlIiwiaXNzIjoic3VwYWJhc2UiLCJpYXQiOjE3ODM1ODU3MTEsImV4cCI6MTk0MTI2NTcxMX0.VWrE9QCrq1qtopp4nVi7awL5gvP0U0DvX61nTC4sffg';
    private const WACRM_ENCRYPTION_KEY = 'dbe313333e89b96f2d1b2b950470503505a0c9427f855cff923085038844902b';
    private const WACRM_ACCOUNT_ID = '2b9216f2-3103-456f-9608-d4a16f4ede93';

    public function __invoke(Request $request, string $mediaId)
    {
        // Require agent API key auth
        $token = $request->bearerToken();
        if (!$token || !str_starts_with($token, 'mng_sk_')) {
            abort(401, 'API key required');
        }

        $apiKey = AgentApiKey::findByKey($token);
        if (!$apiKey || !$apiKey->isValid()) {
            abort(401, 'Invalid API key');
        }

        try {
            // 1. Get WhatsApp config from WACRM Supabase
            $configResp = Http::withHeaders([
                'apikey' => self::SUPABASE_SERVICE_KEY,
                'Authorization' => 'Bearer ' . self::SUPABASE_SERVICE_KEY,
            ])->get(self::SUPABASE_URL . '/rest/v1/whatsapp_config', [
                'select' => 'access_token',
                'account_id' => 'eq.' . self::WACRM_ACCOUNT_ID,
                'limit' => '1',
            ]);

            if (!$configResp->successful() || empty($configResp->json())) {
                Log::error('WACRM media: failed to fetch config', ['status' => $configResp->status()]);
                abort(500, 'Failed to fetch WhatsApp config');
            }

            $accessToken = $this->decryptValue($configResp->json()[0]['access_token'] ?? '');

            // 2. Resolve media URL from Meta
            $metaResp = Http::withToken($accessToken)
                ->timeout(15)
                ->get("https://graph.facebook.com/v21.0/{$mediaId}");

            if (!$metaResp->successful()) {
                Log::error('WACRM media: Meta resolve failed', ['mediaId' => $mediaId, 'status' => $metaResp->status()]);
                abort(404, 'Media not found on Meta');
            }

            $metaData = $metaResp->json();
            $downloadUrl = $metaData['url'] ?? '';
            $mimeType = $metaData['mime_type'] ?? 'application/octet-stream';

            if (!$downloadUrl) {
                abort(404, 'Media download URL not found');
            }

            // 3. Download the binary
            $mediaResp = Http::withToken($accessToken)
                ->timeout(60)
                ->get($downloadUrl);

            if (!$mediaResp->successful()) {
                Log::error('WACRM media: download failed', ['mediaId' => $mediaId, 'status' => $mediaResp->status()]);
                abort(500, 'Failed to download media');
            }

            $body = $mediaResp->body();

            Log::info('WACRM media: served', [
                'mediaId' => $mediaId,
                'size' => strlen($body),
                'mime' => $mimeType,
            ]);

            return response($body, 200, [
                'Content-Type' => $mimeType,
                'Content-Length' => strlen($body),
                'Cache-Control' => 'public, max-age=86400',
            ]);

        } catch (\Throwable $e) {
            Log::error('WACRM media proxy failed', [
                'mediaId' => $mediaId,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Media proxy failed');
        }
    }

    /**
     * Decrypt a WACRM AES-256-GCM encrypted value.
     */
    private function decryptValue(string $encrypted): string
    {
        $parts = explode(':', $encrypted);
        if (count($parts) !== 3) {
            return $encrypted;
        }

        $iv = hex2bin($parts[0]);
        $ciphertext = hex2bin($parts[1]);
        $tag = hex2bin($parts[2]);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            hex2bin(self::WACRM_ENCRYPTION_KEY),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $decrypted ?: $encrypted;
    }
}
