<?php

namespace App\Services;

use App\Models\MatchingFeePayment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Flutterwave Pay with Bank Transfer (PWBT) service.
 *
 * PAYS INTO A FIXED VIRTUAL ACCOUNT (since 2026-09-16).
 *
 * Previously this minted a single-use dynamic account per attempt with a
 * 30-minute expiry. That failed in production for every customer: an agent
 * retry issued a *different* account number each time, customers transferred
 * to a number that had already expired, and their banking app rejected it
 * ("PWBT attempts 97 and 98 failed in banking app" — MAI-1347). Across the
 * whole life of the integration not one customer payment ever landed: 85
 * pending rows, 0 paid, and Flutterwave's own transaction history held nothing
 * but test transfers from the account owner.
 *
 * The fixed account below is a real merchant account on the Digital20
 * Flutterwave profile. Verified live via /accounts/resolve on 2026-09-16:
 *   9969933815 / 090567 -> "Digital20 Limited MaidsSubAccount"
 * It never expires, so a customer can pay whenever they are ready and an agent
 * can re-read the same number back to them without changing it.
 *
 * The cost of a fixed account is that inbound transfers carry no tx_ref of
 * ours — Flutterwave stamps its own ("1789553790430-RND_209"). Attribution is
 * therefore done by reconcileStatic(): amount + virtual account + time window,
 * with each Flutterwave transaction id claimable exactly once (enforced by a
 * unique index on flutterwave_tx_id).
 */
class FlutterwavePwbtService
{
    /**
     * The fixed virtual account customers pay into. Verified against
     * POST /v3/accounts/resolve — do not edit without re-resolving, a wrong
     * bank name here means every customer's transfer bounces.
     */
    public const STATIC_ACCOUNT_NUMBER = '9969933815';
    public const STATIC_ACCOUNT_BANK   = 'Flutterwave MFB (Formerly OK MFB)';
    public const STATIC_BANK_CODE      = '090567';
    public const STATIC_ACCOUNT_NAME   = 'Digital20 Limited MaidsSubAccount';

    /** How far before a payment row was created we will look for its transfer. */
    private const MATCH_LOOKBACK_MINUTES = 20;

    private string $secretKey;
    private string $baseUrl;

    /**
     * Per-instance memo of fixed-account transfer pulls, keyed by window.
     *
     * The backstop cron reconciles every pending payment on each pass. Without
     * this, a sweep of 85 pending rows meant 85 identical calls to Flutterwave
     * every five minutes. The window only ever widens backwards, so one pull
     * covering the earliest window serves every payment in the run.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $txCache = [];

    public function __construct()
    {
        $this->baseUrl = config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3');
        $this->secretKey = $this->resolveSecretKey();
    }

    /**
     * Resolve the Flutterwave secret key from the layered stores, tolerating the
     * historically corrupted values (DB settings / .env hold Laravel-encrypted
     * blobs, some of them encrypted more than once under APP_KEYs that are now
     * gone). We try, in order: the DB setting, then config/env, and for each we
     * peel up to 2 Crypt layers. A real key looks like "FLWSECK-..." /
     * "FLWSECK_TEST-...". Anything else is treated as "not configured" so the
     * caller fails loudly instead of sending ciphertext to Flutterwave (401).
     */
    private function resolveSecretKey(): string
    {
        $candidates = [
            Setting::get('flutterwave_secret_key', null),
            config('services.flutterwave.secret_key'),
            env('FLUTTERWAVE_SECRET_KEY'),
        ];

        foreach ($candidates as $raw) {
            $key = $this->unwrap((string) $raw);
            if ($this->looksLikeFlwKey($key)) {
                return $key;
            }
        }

        Log::warning('Flutterwave secret key could not be resolved to a real key — '
            . 'PWBT is disabled until FLUTTERWAVE_SECRET_KEY is set to a valid FLWSECK- key '
            . '(the stored value is an unrecoverable multi-encrypted blob).');

        return '';
    }

    /** Peel up to 2 Laravel-Crypt layers off a value; return the first plaintext-ish result. */
    private function unwrap(string $value): string
    {
        $value = trim($value);
        for ($i = 0; $i < 2; $i++) {
            if ($this->looksLikeFlwKey($value) || $value === '') {
                return $value;
            }
            try {
                $value = trim(Crypt::decryptString($value));
            } catch (\Throwable $e) {
                break;
            }
        }
        return $value;
    }

    private function looksLikeFlwKey(string $v): bool
    {
        return $v !== ''
            && ! str_contains($v, 'xxxx')
            && preg_match('/^FLWSECK[_-]/', $v) === 1;
    }

    /**
     * Generate a PWBT virtual account for a user's matching fee payment.
     */
    /**
     * @param bool $newRequest  This is a SEPARATE hire, not a repeat of one
     *                          already paid for. Employers routinely want more
     *                          than one helper — MAI-1209 is three housekeepers
     *                          for one household, MNG-7 is two nannies — and the
     *                          matching fee is charged per helper, per request.
     *                          Without this the second hire is free.
     */
    public function generateForUser(User $user, int $amount = 20000, bool $newRequest = false): array
    {
        if (empty($this->secretKey)) {
            throw new \RuntimeException('PWBT_UNAVAILABLE: Flutterwave secret key is not configured. '
                . 'Do not retry — use the manual payment fallback and alert the team.');
        }

        // Query the table rather than reading $user->latestPreference: the
        // relationship is cached on the model instance, so a second call within
        // the same request sees a stale null and creates *another* preference.
        // That is the duplication Operations reported on MAI-1347 (preference
        // 379 minted alongside the still-correct 378), and testing this flow
        // reproduced it 17 times over.
        $preference = \App\Models\EmployerPreference::where('employer_id', $user->id)
            ->latest('id')
            ->first();

        // A separate hire gets its own preference: it is a distinct request, with
        // its own fee, its own match and its own fulfillment case.
        if ($newRequest) {
            $preference = null;
        }

        // Auto-create a minimal preference if the user genuinely has none.
        if (!$preference) {
            $preference = \App\Models\EmployerPreference::create([
                'employer_id' => $user->id,
                'help_types' => json_encode([]),
                'location' => null,
                'quiz_status' => 'in_progress',
                'matching_status' => 'pending',
            ]);
            Log::info('Created minimal preference for user for PWBT payment', [
                'user_id' => $user->id,
                'preference_id' => $preference->id,
            ]);
        }

        // Reuse an open payment rather than minting another one. This is the
        // fix for the retry storm: employer 460 accumulated eight pending rows
        // (and eight different account numbers) in one evening because every
        // agent retry created a fresh row. With a fixed account there is
        // nothing to re-issue, so hand the same row back.
        // A second helper is a second fee — but "already paid" stays scoped to
        // the employer, not the preference. Fatima's fee sits on preference 378
        // while her latest is 379 (duplicates created before the preference bug
        // was fixed), so a preference-scoped check would miss a real payment and
        // bill her twice for the same hire. $newRequest is what distinguishes an
        // additional helper from a repeat of the same one, and that is a
        // decision only the agent talking to her can make.
        $existing = $newRequest ? null : MatchingFeePayment::where('employer_id', $user->id)
            ->where('payment_type', 'matching_fee')
            ->whereIn('status', ['paid', 'completed'])
            ->first();

        if ($existing) {
            Log::info('Flutterwave PWBT: employer already paid, returning settled payment', [
                'user_id' => $user->id, 'payment_id' => $existing->id,
            ]);
            return $this->presentAccount($existing, $user, (int) $existing->amount, true);
        }

        $payment = $newRequest ? null : MatchingFeePayment::where('employer_id', $user->id)
            ->where('payment_type', 'matching_fee')
            ->where('status', 'pending')
            ->where('account_number', self::STATIC_ACCOUNT_NUMBER)
            ->orderByDesc('id')
            ->first();

        if ($payment) {
            Log::info('Flutterwave PWBT: reusing open payment', [
                'user_id' => $user->id, 'payment_id' => $payment->id, 'tx_ref' => $payment->tx_ref,
            ]);
            return $this->presentAccount($payment, $user, (int) $payment->amount, false);
        }

        // No open row — create one. We still mint a tx_ref: Flutterwave will not
        // carry it on a fixed-account transfer, but it remains our internal
        // reference, our idempotency key, and what the agent quotes to the
        // customer as the narration.
        $txRef = $this->generateTxRef($user->id);

        // Attach the fee to its request. Revenue is reported per request, so a
        // payment with no request is money the dashboard cannot attribute.
        $hireRequest = \App\Models\HireRequest::where('employer_id', $user->id)
            ->where('preference_id', $preference?->id)
            ->whereIn('status', \App\Models\HireRequest::OPEN_STATUSES)
            ->latest()
            ->first();

        if (!$hireRequest) {
            $hireRequest = \App\Models\HireRequest::open([
                'employer_id'   => $user->id,
                'preference_id' => $preference?->id,
                'fee_amount'    => $amount,
                'status'        => 'open',
            ]);
        }

        $payment = MatchingFeePayment::create([
            'preference_id'   => $preference?->id,
            'hire_request_id' => $hireRequest->id,
            'employer_id'     => $user->id,
            'amount'          => $amount,
            'reference'       => $txRef,
            'gateway'         => 'flutterwave',
            'payment_type'    => 'matching_fee',
            'status'          => 'pending',
            'tx_ref'          => $txRef,
            'account_number'  => self::STATIC_ACCOUNT_NUMBER,
            'account_bank'    => self::STATIC_ACCOUNT_BANK,
            'account_name'    => self::STATIC_ACCOUNT_NAME,
            'expires_at'      => null,   // fixed account: never expires
        ]);

        Log::info('Flutterwave PWBT: issued fixed account', [
            'tx_ref' => $txRef, 'user_id' => $user->id, 'payment_id' => $payment->id,
        ]);

        return $this->presentAccount($payment, $user, $amount, false);
    }

    /**
     * Shape a payment row into the agent-facing payload. One place, so a reused
     * row and a fresh row are always presented identically.
     */
    private function presentAccount(MatchingFeePayment $payment, User $user, int $amount, bool $alreadyPaid): array
    {
        return [
            'payment_id'     => $payment->id,
            'tx_ref'         => $payment->tx_ref,
            'amount'         => $amount,
            'currency'       => 'NGN',
            'account_number' => self::STATIC_ACCOUNT_NUMBER,
            'account_bank'   => self::STATIC_ACCOUNT_BANK,
            'account_name'   => self::STATIC_ACCOUNT_NAME,
            'expires_at'     => null,
            'already_paid'   => $alreadyPaid,
            'whatsapp_text'  => $alreadyPaid
                ? "Good news — we already have your ₦" . number_format($amount) . " matching fee on record. Nothing further to pay."
                : $this->formatWhatsAppMessage($amount, $user->name),
        ];
    }

    /**
     * Format the fixed bank details for WhatsApp.
     *
     * Two things here are load-bearing. The account name must be stated up
     * front — customers abandoned transfers because "Digital20 Limited" is not
     * the name they expected to see for Maids.ng. And there is deliberately no
     * expiry language any more: the old copy said "expires in 30 minutes",
     * which pressured people into rushing a transfer to a number that had often
     * already lapsed by the time they read it.
     */
    private function formatWhatsAppMessage(int $amount, string $name): string
    {
        $first = trim(explode(' ', trim($name))[0] ?? '');
        $greeting = $first !== '' ? "{$first}, here" : 'Here';

        return "{$greeting} are the account details for your ₦" . number_format($amount) . " matching fee:\n\n"
            . "*Bank:* " . self::STATIC_ACCOUNT_BANK . "\n"
            . "*Account Number:* *" . self::STATIC_ACCOUNT_NUMBER . "*\n"
            . "*Account Name:* " . self::STATIC_ACCOUNT_NAME . "\n"
            . "*Amount:* ₦" . number_format($amount) . "\n\n"
            . "Please use your own name or phone number as the transfer narration so we can match it to you quickly.\n\n"
            . "This account does not expire — you can pay whenever you are ready. "
            . "Once you have transferred, reply \"done\" and I will confirm it.\n\n"
            . "Note: Maids.ng is a subsidiary of Digital20 Limited, so that is the name that appears in your banking app. This is normal.";
    }

    public function verifyTransaction(string $txRef): ?array
    {
        $response = Http::withToken($this->secretKey)
            ->timeout(30)
            ->get($this->baseUrl . '/transactions/verify_by_reference', [
                'tx_ref' => $txRef,
            ]);

        if (!$response->successful()) {
            Log::warning('Flutterwave PWBT: verification failed', [
                'tx_ref' => $txRef,
                'status' => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();
        $txData = $data['data'] ?? [];

        $txStatus = $txData['status'] ?? '';

        Log::info('Flutterwave PWBT: verification result', [
            'tx_ref' => $txRef,
            'status' => $txStatus,
            'amount' => $txData['amount'] ?? 0,
        ]);

        if ($txStatus === 'successful') {
            return $txData;
        }

        return null;
    }

    /**
     * Pull a pending PWBT payment's status straight from Flutterwave and, if it
     * has been paid for real, settle it (mark paid + preference status +
     * PaymentConfirmed event). Idempotent. This is the source of truth — we do
     * not depend on the Flutterwave webhook because the same Flutterwave account
     * serves several Digital20 brands and its single webhook URL may not point
     * at Maids.ng.
     *
     * Isolation is by tx_ref: only Maids.ng mints "MNG-<userId>-..." refs, so a
     * sibling brand's transaction never resolves to a row here.
     *
     * @return array{status:string, paid:bool, paid_amount?:int, expected_amount?:int}
     *   status ∈ already|paid|pending|amount_mismatch|ref_mismatch|gateway_error
     */
    public function reconcile(MatchingFeePayment $payment): array
    {
        if (in_array($payment->status, ['paid', 'completed'], true)) {
            return ['status' => 'already', 'paid' => true];
        }

        // Fixed-account payments carry no reference of ours at Flutterwave, so
        // verify_by_reference can never find them — attribute by amount, account
        // and window instead. Legacy rows from the dynamic-account era still
        // resolve the old way.
        if ($payment->account_number === self::STATIC_ACCOUNT_NUMBER) {
            return $this->reconcileStatic($payment);
        }

        if (empty($payment->tx_ref)) {
            return ['status' => 'gateway_error', 'paid' => false];
        }

        try {
            $tx = $this->verifyTransaction($payment->tx_ref);
        } catch (\Throwable $e) {
            Log::warning('PWBT reconcile: gateway call failed', ['tx_ref' => $payment->tx_ref, 'error' => $e->getMessage()]);
            return ['status' => 'gateway_error', 'paid' => false];
        }

        if (!$tx) {
            return ['status' => 'pending', 'paid' => false];
        }

        $paidAmount = (int) round((float) ($tx['amount'] ?? 0));
        $currency   = strtoupper((string) ($tx['currency'] ?? 'NGN'));
        $txRef      = (string) ($tx['tx_ref'] ?? '');

        if ($txRef !== '' && $txRef !== $payment->tx_ref) {
            Log::warning('PWBT reconcile: tx_ref mismatch', ['expected' => $payment->tx_ref, 'got' => $txRef]);
            return ['status' => 'ref_mismatch', 'paid' => false];
        }
        if ($currency !== 'NGN' || $paidAmount < (int) $payment->amount) {
            Log::warning('PWBT reconcile: amount/currency mismatch', [
                'tx_ref' => $payment->tx_ref, 'expected' => $payment->amount,
                'paid' => $paidAmount, 'currency' => $currency,
            ]);
            return [
                'status' => 'amount_mismatch', 'paid' => false,
                'paid_amount' => $paidAmount, 'expected_amount' => (int) $payment->amount,
            ];
        }

        $payment->update([
            'status'            => 'paid',
            'paid_at'           => now(),
            'gateway_response'  => $tx,
            'flutterwave_tx_id' => (string) ($tx['id'] ?? $payment->flutterwave_tx_id ?? ''),
        ]);

        if ($payment->preference) {
            $payment->preference->update([
                'matching_status' => $payment->payment_type === 'guarantee_match' ? 'guarantee_paid' : 'paid',
            ]);
        }

        // Advance the request the moment the fee is confirmed, so "paid" on the
        // dashboard means money received rather than somebody remembering.
        if ($payment->hire_request_id) {
            $req = \App\Models\HireRequest::find($payment->hire_request_id);
            if ($req && !$req->paid_at) {
                $req->update([
                    'paid_at' => now(),
                    'status'  => in_array($req->status, ['open'], true) ? 'paid' : $req->status,
                ]);
            }
        }

        if ($payment->employer) {
            \App\Events\PaymentConfirmed::dispatch(
                $payment->employer,
                $payment->reference ?? $payment->tx_ref,
                (int) $payment->amount,
                $payment->payment_type ?? 'matching_fee',
            );
        }

        Log::info('PWBT reconcile: payment confirmed', [
            'payment_id' => $payment->id, 'tx_ref' => $payment->tx_ref, 'user_id' => $payment->employer_id,
        ]);

        return ['status' => 'paid', 'paid' => true];
    }

    /**
     * Pull recent successful transfers into the fixed virtual account.
     *
     * Flutterwave has no "transactions for virtual account X" endpoint, so we
     * pull the window and filter on meta.virtualaccountnumber ourselves.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchStaticAccountTransactions(\DateTimeInterface $from, ?\DateTimeInterface $to = null): array
    {
        $to ??= now();

        $cacheKey = $from->format('Y-m-d') . '|' . $to->format('Y-m-d');
        if (isset($this->txCache[$cacheKey])) {
            return $this->txCache[$cacheKey];
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(40)
                ->get($this->baseUrl . '/transactions', [
                    'from' => $from->format('Y-m-d'),
                    'to'   => $to->format('Y-m-d'),
                ]);
        } catch (\Throwable $e) {
            Log::warning('PWBT static: transaction pull failed', ['error' => $e->getMessage()]);
            return $this->txCache[$cacheKey] = [];
        }

        if (!$response->successful()) {
            Log::warning('PWBT static: transaction pull returned error', ['status' => $response->status()]);
            return $this->txCache[$cacheKey] = [];
        }

        $out = [];
        foreach (($response->json()['data'] ?? []) as $tx) {
            if (strtolower((string) ($tx['status'] ?? '')) !== 'successful') {
                continue;
            }
            if (strtoupper((string) ($tx['currency'] ?? 'NGN')) !== 'NGN') {
                continue;
            }
            $va = (string) ($tx['meta']['virtualaccountnumber'] ?? '');
            if ($va !== self::STATIC_ACCOUNT_NUMBER) {
                continue;
            }
            $out[] = $tx;
        }

        return $this->txCache[$cacheKey] = $out;
    }

    /**
     * Settle a pending payment against the fixed virtual account.
     *
     * A fixed-account transfer carries no reference of ours, so we attribute on
     * three signals and refuse to guess beyond them:
     *
     *   1. the transfer landed on OUR virtual account;
     *   2. it is for at least the expected amount, in NGN;
     *   3. it happened inside this payment's window (shortly before the row was
     *      created, through now).
     *
     * Every Flutterwave transaction id may be claimed by exactly one payment
     * row — enforced by a unique index on flutterwave_tx_id, so two concurrent
     * reconciles cannot both bank the same transfer. When several customers
     * have an open payment for the same amount we prefer the one whose name
     * matches the sender, and otherwise settle oldest-first rather than
     * arbitrarily.
     *
     * @return array{status:string, paid:bool, paid_amount?:int, expected_amount?:int, tx_id?:string}
     *   status ∈ already|paid|pending|ambiguous|gateway_error
     */
    public function reconcileStatic(MatchingFeePayment $payment): array
    {
        if (in_array($payment->status, ['paid', 'completed'], true)) {
            return ['status' => 'already', 'paid' => true];
        }

        $from = $payment->created_at
            ? $payment->created_at->copy()->subMinutes(self::MATCH_LOOKBACK_MINUTES)
            : now()->subDays(7);

        $candidates = $this->fetchStaticAccountTransactions($from);
        if (!$candidates) {
            return ['status' => 'pending', 'paid' => false];
        }

        $expected = (int) $payment->amount;
        $claimed  = MatchingFeePayment::whereNotNull('flutterwave_tx_id')
            ->where('flutterwave_tx_id', '!=', '')
            ->pluck('flutterwave_tx_id')
            ->map(fn ($v) => (string) $v)
            ->all();

        $viable = [];
        foreach ($candidates as $tx) {
            $txId = (string) ($tx['id'] ?? '');
            if ($txId === '' || in_array($txId, $claimed, true)) {
                continue;   // already banked by another payment row
            }
            if ((int) round((float) ($tx['amount'] ?? 0)) < $expected) {
                continue;   // underpayment — never auto-settle
            }
            $createdAt = strtotime((string) ($tx['created_at'] ?? ''));
            if ($createdAt === false || $createdAt < $from->getTimestamp()) {
                continue;   // predates this payment's window
            }
            $viable[$createdAt] = $tx;
        }

        if (!$viable) {
            return ['status' => 'pending', 'paid' => false];
        }

        ksort($viable);

        // Prefer a transfer whose sender name looks like this customer; a
        // Nigerian bank narration usually carries the originator's real name.
        $match = null;
        $customer = $this->nameTokens((string) ($payment->employer->name ?? ''));
        if ($customer) {
            foreach ($viable as $tx) {
                $sender = $this->nameTokens((string) ($tx['meta']['originatorname'] ?? ''));
                if ($sender && array_intersect($customer, $sender)) {
                    $match = $tx;
                    break;
                }
            }
        }

        // No name signal. If other customers are also waiting on the same
        // amount we cannot safely attribute — say so rather than credit the
        // wrong person's account.
        if (!$match) {
            $competing = MatchingFeePayment::where('status', 'pending')
                ->where('account_number', self::STATIC_ACCOUNT_NUMBER)
                ->where('amount', $expected)
                ->where('id', '!=', $payment->id)
                ->where('created_at', '>=', $from)
                ->count();

            if ($competing > 0 && count($viable) < ($competing + 1)) {
                Log::warning('PWBT static: ambiguous attribution, needs a human', [
                    'payment_id' => $payment->id,
                    'transfers'  => count($viable),
                    'competing'  => $competing,
                ]);
                return ['status' => 'ambiguous', 'paid' => false];
            }

            $match = reset($viable);
        }

        return $this->settle($payment, $match);
    }

    /**
     * Mark a payment paid against a Flutterwave transaction, claiming the
     * transaction id so nothing else can bank it. Idempotent.
     *
     * @param array<string, mixed> $tx
     * @return array{status:string, paid:bool, tx_id?:string}
     */
    private function settle(MatchingFeePayment $payment, array $tx): array
    {
        $txId = (string) ($tx['id'] ?? '');

        try {
            $payment->update([
                'status'            => 'paid',
                'paid_at'           => now(),
                'gateway_response'  => $tx,
                'flutterwave_tx_id' => $txId,
                'flw_ref'           => (string) ($tx['flw_ref'] ?? '') ?: null,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique violation on flutterwave_tx_id or flw_ref: another
            // reconcile claimed this transfer first. Correct outcome, not an error.
            Log::info('PWBT static: transaction already claimed elsewhere', [
                'payment_id' => $payment->id, 'tx_id' => $txId,
            ]);
            return ['status' => 'pending', 'paid' => false];
        }

        if ($payment->preference) {
            $payment->preference->update([
                'matching_status' => $payment->payment_type === 'guarantee_match' ? 'guarantee_paid' : 'paid',
            ]);
        }

        if ($payment->employer) {
            \App\Events\PaymentConfirmed::dispatch(
                $payment->employer,
                $payment->reference ?? $payment->tx_ref,
                (int) $payment->amount,
                $payment->payment_type ?? 'matching_fee',
            );
        }

        Log::info('PWBT static: payment confirmed', [
            'payment_id' => $payment->id,
            'user_id'    => $payment->employer_id,
            'tx_id'      => $txId,
            'sender'     => $tx['meta']['originatorname'] ?? null,
        ]);

        return ['status' => 'paid', 'paid' => true, 'tx_id' => $txId];
    }

    /**
     * Find the inbound transfer that matches the evidence a customer gave us.
     *
     * Reconciliation by amount + window alone cannot tell two ₦20,000 transfers
     * apart. What does tell them apart is what the payer can see on their own
     * bank receipt, so we ask for it and match on it:
     *
     *   session_id  — the receipt's "Session ID" / transaction reference. This
     *                 is the NIBSS session id, and Flutterwave stores the same
     *                 string as flw_ref, so it is an exact key shared by the
     *                 customer, Flutterwave and us. Decisive on its own.
     *   sender_name — matched against meta.originatorname.
     *   sender_bank — matched against meta.bankname (OPAY, KUDA, GTB…).
     *
     * A session id match is returned immediately. Otherwise name and bank must
     * BOTH agree, and if that still leaves more than one candidate we report
     * ambiguity rather than pick one.
     *
     * @param  array{session_id?:?string, sender_name?:?string, sender_bank?:?string} $evidence
     * @return array{status:string, transaction?:array<string,mixed>, candidates?:int}
     *   status ∈ matched|not_found|ambiguous|already_claimed
     */
    public function findTransferByEvidence(MatchingFeePayment $payment, array $evidence): array
    {
        $from = $payment->created_at
            ? $payment->created_at->copy()->subMinutes(self::MATCH_LOOKBACK_MINUTES)
            : now()->subDays(14);

        // A customer often pays before the agent opens the record, and receipts
        // arrive late, so search generously — the evidence is what narrows it,
        // not the window.
        $from = $from->min(now()->subDays(14));

        $transactions = $this->fetchStaticAccountTransactions($from);
        if (!$transactions) {
            return ['status' => 'not_found'];
        }

        $claimedTxIds  = MatchingFeePayment::whereNotNull('flutterwave_tx_id')
            ->where('flutterwave_tx_id', '!=', '')->pluck('flutterwave_tx_id')->map(fn ($v) => (string) $v)->all();
        $claimedFlwRef = MatchingFeePayment::whereNotNull('flw_ref')
            ->where('flw_ref', '!=', '')->pluck('flw_ref')->map(fn ($v) => (string) $v)->all();

        $sessionId = $this->digitsOnly((string) ($evidence['session_id'] ?? ''));
        $wantName  = $this->nameTokens((string) ($evidence['sender_name'] ?? ''));
        $wantBank  = $this->bankToken((string) ($evidence['sender_bank'] ?? ''));

        $matches = [];

        foreach ($transactions as $tx) {
            $txId   = (string) ($tx['id'] ?? '');
            $flwRef = (string) ($tx['flw_ref'] ?? '');

            $isClaimed = ($txId !== '' && in_array($txId, $claimedTxIds, true))
                      || ($flwRef !== '' && in_array($flwRef, $claimedFlwRef, true));

            // The session id is decisive — report it even when already banked,
            // so an agent chasing a receipt learns the money is accounted for
            // instead of being told it does not exist.
            if ($sessionId !== '' && $this->digitsOnly($flwRef) === $sessionId) {
                return $isClaimed
                    ? ['status' => 'already_claimed', 'transaction' => $tx]
                    : ['status' => 'matched', 'transaction' => $tx];
            }

            if ($isClaimed) {
                continue;
            }
            if ((int) round((float) ($tx['amount'] ?? 0)) < (int) $payment->amount) {
                continue;
            }

            // Without a session id, name and bank must both agree.
            if (!$wantName && !$wantBank) {
                continue;
            }

            $gotName = $this->nameTokens((string) ($tx['meta']['originatorname'] ?? ''));
            $gotBank = $this->bankToken((string) ($tx['meta']['bankname'] ?? ''));

            $nameOk = $wantName && $gotName && array_intersect($wantName, $gotName);
            $bankOk = $wantBank && $gotBank && $wantBank === $gotBank;

            if ($nameOk && $bankOk) {
                $matches[] = $tx;
            }
        }

        if (!$matches) {
            return ['status' => 'not_found'];
        }
        if (count($matches) > 1) {
            return ['status' => 'ambiguous', 'candidates' => count($matches)];
        }

        return ['status' => 'matched', 'transaction' => $matches[0]];
    }

    /**
     * Settle a payment against a transfer the customer's own evidence identified.
     *
     * @param array<string, mixed> $tx
     * @return array{status:string, paid:bool, tx_id?:string}
     */
    public function settleFromEvidence(MatchingFeePayment $payment, array $tx): array
    {
        return $this->settle($payment, $tx);
    }

    /** Digits only — receipts print session ids with spaces and dashes. */
    private function digitsOnly(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    /**
     * Reduce a bank name to a comparable token. Customers say "Opay", receipts
     * print "OPAY Digital Services", Flutterwave returns "OPAY"; "GTBank",
     * "GTB" and "Guaranty Trust" are all the same bank to a payer.
     */
    private function bankToken(string $bank): string
    {
        $b = strtolower(preg_replace('/[^a-z]/i', '', $bank) ?? '');
        if ($b === '') {
            return '';
        }

        $aliases = [
            'opay'          => ['opay', 'opaydigitalservices', 'opaydigital'],
            'palmpay'       => ['palmpay'],
            'kuda'          => ['kuda', 'kudamfb', 'kudabank'],
            'moniepoint'    => ['moniepoint', 'moniepointmfb'],
            'gtbank'        => ['gtb', 'gtbank', 'guarantytrust', 'guarantytrustbank'],
            'access'        => ['access', 'accessbank', 'accessdiamond'],
            'zenith'        => ['zenith', 'zenithbank'],
            'uba'           => ['uba', 'unitedbankforafrica'],
            'firstbank'     => ['firstbank', 'firstbankofnigeria', 'fbn'],
            'sterling'      => ['sterling', 'sterlingbank'],
            'fcmb'          => ['fcmb', 'firstcitymonumentbank'],
            'union'         => ['union', 'unionbank'],
            'stanbic'       => ['stanbic', 'stanbicibtc'],
            'fidelity'      => ['fidelity', 'fidelitybank'],
            'wema'          => ['wema', 'wemabank', 'alat'],
            'polaris'       => ['polaris', 'polarisbank'],
            'ecobank'       => ['ecobank'],
            'keystone'      => ['keystone', 'keystonebank'],
            'flutterwavemfb'=> ['flutterwave', 'flutterwavemfb', 'okmfb'],
        ];

        foreach ($aliases as $canonical => $forms) {
            foreach ($forms as $f) {
                if ($b === $f || str_starts_with($b, $f)) {
                    return $canonical;
                }
            }
        }

        return $b;
    }

    /**
     * Comparable name tokens, so "FATIMA BELLO" matches "Bello Fatima".
     * Tokens shorter than 3 characters are dropped — initials collide freely.
     *
     * @return array<int, string>
     */
    private function nameTokens(string $name): array
    {
        $parts = preg_split('/[^a-z]+/i', strtolower(trim($name))) ?: [];
        return array_values(array_filter($parts, fn ($p) => strlen($p) >= 3));
    }

    private function generateTxRef(int $userId): string
    {
        return 'MNG-' . $userId . '-' . now()->format('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^\d]/', '', $phone);

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '234' . substr($digits, 1);
        }

        return $digits;
    }
}
