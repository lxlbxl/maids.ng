<?php

namespace App\Http\Controllers\Api;

use App\Services\MetaCapi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * CtwaClidController — the WhatsApp→Paperclip bridge calls this the first time
 * a customer messages Peace after tapping a Click-to-WhatsApp ad. We stash the
 * ad-click id (ctwa_clid) keyed by the customer's phone for 7 days, so that when
 * they later pay a matching fee on the site, SendPurchaseToMetaCapi can send an
 * extra business_messaging Purchase that attributes the sale to the ad.
 *
 * Auth: shared secret in the X-CTWA-Secret header (config services.meta.ctwa_secret).
 */
class CtwaClidController extends ApiController
{
    public function __invoke(Request $request, MetaCapi $capi): JsonResponse
    {
        $secret = config('services.meta.ctwa_secret');
        if (! $secret || ! hash_equals($secret, (string) $request->header('X-CTWA-Secret'))) {
            return $this->error('Unauthorized.', Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'phone'     => 'required|string|max:32',
            'ctwa_clid' => 'required|string|max:512',
            'source_id' => 'nullable|string|max:128',
        ]);

        $capi->storeCtwaClid($validated['phone'], $validated['ctwa_clid']);

        Log::info('CTWA clid stored', [
            'phone'     => preg_replace('/\d(?=\d{4})/', '*', $validated['phone']),
            'source_id' => $validated['source_id'] ?? null,
        ]);

        return $this->success(['stored' => true], 'CTWA click id stored.');
    }
}
