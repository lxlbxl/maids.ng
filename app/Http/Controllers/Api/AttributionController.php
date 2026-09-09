<?php

namespace App\Http\Controllers\Api;

use App\Services\Attribution\AttributionClassifier;
use App\Services\Attribution\AttributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * The wa-paperclip-bridge calls this once per new inbound WhatsApp conversation:
 * it hands us the phone plus whatever attribution signal the message carried (a
 * click `code` from the "[src:source/code]" tag, or Meta's `referral` object for
 * a Click-to-WhatsApp ad, or nothing). We resolve it, stash it against the phone
 * so UserController@store can pick it up later, and return a human-readable
 * summary for the bridge to write onto the Paperclip issue.
 *
 * Auth: shared secret in X-Attribution-Secret (config services.attribution.bridge_secret).
 */
class AttributionController extends ApiController
{
    public function ingest(Request $request, AttributionService $svc): JsonResponse
    {
        $secret = config('services.attribution.bridge_secret');
        if (! $secret || ! hash_equals($secret, (string) $request->header('X-Attribution-Secret'))) {
            return $this->error('Unauthorized.', Response::HTTP_UNAUTHORIZED);
        }

        $data = $request->validate([
            'phone'    => 'required|string|max:32',
            'code'     => 'nullable|string|max:16',
            'referral' => 'nullable|array',
            'source'   => 'nullable|string|max:40',
        ]);

        $waClickId = null;
        if (! empty($data['code'])) {
            $attribution = $svc->resolveCode($data['code']);
            $waClickId = $attribution['wa_click_id'] ?? null;
        } elseif (! empty($data['referral'])) {
            $attribution = $svc->fromCtwaReferral($data['referral']);
        } else {
            $attribution = null;
        }

        // Bare "[src:x]" with no code, or nothing at all.
        if (! $attribution) {
            $attribution = AttributionService::whatsappDirect();
            if (! empty($data['source'])) {
                $attribution['source'] = $data['source'];
                $attribution['channel'] = 'whatsapp_direct';
            }
        }

        $svc->stampLead($data['phone'], $attribution, $waClickId);

        $channel = $attribution['channel'] ?? 'whatsapp_direct';
        $summary = AttributionClassifier::label($channel);
        if (! empty($attribution['campaign'])) {
            $summary .= ' — ' . $attribution['campaign'];
        }
        if (! empty($attribution['utm_source']) && empty($attribution['campaign'])) {
            $summary .= ' — ' . $attribution['utm_source'];
        }

        Log::info('Attribution ingested', [
            'phone'   => preg_replace('/\d(?=\d{4})/', '*', $data['phone']),
            'channel' => $channel,
        ]);

        return $this->success([
            'channel'     => $channel,
            'label'       => AttributionClassifier::label($channel),
            'summary'     => $summary,
            'attribution' => $attribution,
        ], 'Attribution stored.');
    }
}
