<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Services\MetaCapi;

/**
 * PaymentConfirmed -> Meta Conversions API "Purchase".
 *
 * Fires once per confirmed matching-fee / guarantee-match payment, from either
 * the user-facing verify page or the gateway webhook (both dispatch
 * PaymentConfirmed, and MatchingFeeController guards against double-processing
 * a reference). event_id = the payment reference, so it dedupes against a
 * browser Purchase fired with the same reference.
 */
class SendPurchaseToMetaCapi
{
    public function __construct(private readonly MetaCapi $capi)
    {
    }

    public function handle(PaymentConfirmed $event): void
    {
        if (! $this->capi->enabled()) {
            return;
        }

        $custom = [
            'content_name'     => $event->type === 'guarantee_match' ? 'Guarantee Match' : 'Matching Fee',
            'content_category' => 'domestic_staff_matching',
            'order_id'         => $event->reference,
        ];

        $this->capi->purchase(
            user: $event->user,
            eventId: 'purchase_' . $event->reference,
            value: $event->amount,
            currency: 'NGN',
            custom: $custom,
        );

        // Freeze the payer's attribution onto the payment row + fire PostHog.
        try {
            $payment = \App\Models\MatchingFeePayment::where('reference', $event->reference)->first();
            if ($payment && $payment->attribution === null) {
                $snap = app(\App\Services\Attribution\AttributionService::class)
                    ->snapshotForPayment($event->user);
                if ($snap) {
                    $payment->update(['attribution' => $snap]);
                }
                app(\App\Services\PostHog::class)->capture(
                    \App\Services\PostHog::distinctIdForUser($event->user),
                    'matching_fee_paid',
                    [
                        'value'    => $event->amount,
                        'currency' => 'NGN',
                        'type'     => $event->type,
                        'channel'  => $snap['channel'] ?? 'unknown',
                        'campaign' => $snap['campaign'] ?? null,
                        'source'   => $snap['source'] ?? null,
                        'reference'=> $event->reference,
                    ],
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('attribution payment stamp failed: ' . $e->getMessage());
        }

        // Click-to-WhatsApp attribution: if this customer reached us by tapping
        // a WhatsApp ad (the bridge stored their ctwa_clid keyed by phone), send
        // an additional business_messaging Purchase so the CTWA campaign gets
        // credit. Separate event_id suffix — not a duplicate of the website one.
        $phone = (string) ($event->user->phone ?? '');
        if ($phone !== '' && ($clid = $this->capi->ctwaClidForPhone($phone))) {
            $this->capi->purchaseCtwa(
                eventId: 'purchase_ctwa_' . $event->reference,
                ctwaClid: $clid,
                phone: $phone,
                value: $event->amount,
                currency: 'NGN',
                custom: $custom,
            );
        }
    }
}
