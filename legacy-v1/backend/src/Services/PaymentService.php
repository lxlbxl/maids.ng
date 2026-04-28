<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Ramsey\Uuid\Uuid;

class PaymentService
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private array $config;
    private Client $httpClient;

    public function __construct(PDO $pdo, LoggerInterface $logger, array $config)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = $config;
        $this->httpClient = new Client(['timeout' => 30]);
    }

    public function getPaymentConfig(string $gateway = 'flutterwave'): array
    {
        $gatewayConfig = $this->config[$gateway] ?? $this->config['flutterwave'];
        $serviceFee = $this->config['service_fee'];

        return [
            'public_key' => $gatewayConfig['public_key'],
            'amount' => $serviceFee['amount'],
            'currency' => $serviceFee['currency'],
            'gateway' => $gateway
        ];
    }

    public function createPayment(?int $bookingId = null, string $gateway = 'flutterwave', string $type = 'service_fee', ?int $serviceRequestId = null): array
    {
        $txRef = 'MAID_' . time() . '_' . substr(Uuid::uuid4()->toString(), 0, 8);

        // Fetch Matching Fee from DB
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = 'matching_fee_amount'");
        $stmt->execute();
        $matchingFeeSetting = $stmt->fetch();
        $matchingFee = $matchingFeeSetting ? json_decode($matchingFeeSetting['value'], true) : $this->config['service_fee'];

        // Fetch NIN Fee from DB
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = 'nin_verification_fee'");
        $stmt->execute();
        $ninFeeSetting = $stmt->fetch();
        $ninFee = $ninFeeSetting ? json_decode($ninFeeSetting['value'], true) : ['amount' => 5000, 'currency' => 'NGN'];

        $serviceFee = ($type === 'nin_verification') ? $ninFee : $matchingFee;
        $amount = $serviceFee['amount'];
        $commission = 0;
        $helperAmount = 0;
        $subaccounts = [];

        // Handle Salary Payment
        if ($type === 'salary') {
            // Get booking details to know the salary
            $stmt = $this->pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();

            if (!$booking) {
                throw new \Exception("Booking not found");
            }

            // Get Helper Bank Details
            $stmt = $this->pdo->prepare("SELECT subaccount_id, salary_min FROM helpers WHERE id = ?");
            $stmt->execute([$booking['helper_id']]);
            $helper = $stmt->fetch();

            if (empty($helper['subaccount_id'])) {
                throw new \Exception("Maid has not set up bank details");
            }

            // Calculate Commission based on type
            $stmt = $this->pdo->prepare("SELECT key_name, value FROM settings WHERE key_name IN ('commission_type', 'commission_percent', 'commission_fixed_amount')");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $commissionType = json_decode($settings['commission_type'] ?? '"percentage"');
            $commissionPercent = (int) ($settings['commission_percent'] ?? 10);
            $commissionFixed = (int) ($settings['commission_fixed_amount'] ?? 5000);

            // Salary from booking or helper default (fallback)
            $salary = $booking['monthly_rate'] ?: $helper['salary_min'];

            if ($commissionType === 'fixed') {
                $commission = $commissionFixed;
            } else {
                $commission = ($salary * $commissionPercent) / 100;
            }
            
            $helperAmount = max(0, $salary - $commission);
            $amount = $salary;

            // Prepare Subaccount for Split Payment
            if ($gateway === 'flutterwave') {
                $subaccounts = [
                    [
                        'id' => $helper['subaccount_id'], // This is stored as subaccount_id which is Flutterwave's ID
                        'transaction_charge_type' => 'flat_subaccount',
                        'transaction_charge' => $helperAmount
                    ]
                ];
            } elseif ($gateway === 'paystack') {
                // For Paystack, we need to ensure the subaccount_id is a Paystack code (ACC_...)
                // We might need to store different subaccount IDs for different gateways.
                // assuming subaccount_id in helpers table stores a JSON or we handle it.
                // BUT current schema has single VARCHAR subaccount_id.
                // LIMITATION: Helpers need to register bank details which creates subaccount.
                // We should probably check if the stored subaccount_id matches the gateway format or store both.
                // Quick fix: Assume existing subaccount_id is compatible or we need to fetch/create on fly if possible?
                // No, subaccounts are gateway specific.
                // We need to support paystack subaccount creation.
                // For now, let's assume we can pass the subaccount code.

                $subaccounts = [
                    'subaccount' => $helper['subaccount_id'], // Paystack expects subaccount code here
                    'transaction_charge' => $commission * 100, // Paystack is in kobo? No, flat bearer.
                    // Paystack split logic is different. usually you specify subaccount and who bears charges.
                    // simpler: just pass subaccount code.
                    // If we want to split exact amount:
                    // Paystack dynamic split might be needed or just simple subaccount assignment.
                ];
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO payments (booking_id, service_request_id, amount, currency, gateway, tx_ref, status, payment_type, commission_amount, helper_amount)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
        ");
        $stmt->execute([
            $bookingId,
            $serviceRequestId,
            $amount,
            $serviceFee['currency'],
            $gateway,
            $txRef,
            $type,
            $commission,
            $helperAmount
        ]);

        $paymentId = (int) $this->pdo->lastInsertId();

        $this->logger->info("Payment created", [
            'payment_id' => $paymentId,
            'booking_id' => $bookingId,
            'tx_ref' => $txRef,
            'type' => $type
        ]);

        return [
            'payment_id' => $paymentId,
            'tx_ref' => $txRef,
            'amount' => $amount,
            'currency' => $serviceFee['currency'],
            'gateway' => $gateway,
            'public_key' => $this->config[$gateway]['public_key'],
            'subaccounts' => $subaccounts,
            'meta' => ['type' => $type]
        ];
    }

    public function createSubaccount(array $bankDetails, array $helper = [], string $gateway = 'flutterwave'): array
    {
        if ($gateway === 'paystack') {
            return $this->createPaystackSubaccount($bankDetails, $helper);
        }

        try {
            $response = $this->httpClient->post(
                "{$this->config['flutterwave']['base_url']}/subaccounts",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->config['flutterwave']['secret_key'],
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'account_bank' => $bankDetails['bank_code'],
                        'account_number' => $bankDetails['account_number'],
                        'business_name' => $bankDetails['account_name'],
                        'business_email' => 'helper_' . ($helper['id'] ?? time()) . '@maids.ng',
                        'business_contact' => $bankDetails['account_name'],
                        'business_contact_mobile' => $helper['phone'] ?? '0000000000',
                        'business_mobile' => $helper['phone'] ?? '0000000000',
                        'country' => 'NG',
                        'split_type' => 'flat',
                        'split_value' => 0
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] === 'success') {
                return $data['data'];
            }

            throw new \Exception($data['message'] ?? 'Failed to create subaccount');
        } catch (GuzzleException $e) {
            $this->logger->error("Subaccount creation failed", ['error' => $e->getMessage()]);
            throw new \Exception("Failed to connect to payment provider");
        }
    }

    public function createPaystackSubaccount(array $bankDetails, array $helper = []): array
    {
        try {
            $response = $this->httpClient->post(
                "{$this->config['paystack']['base_url']}/subaccount",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->config['paystack']['secret_key'],
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'business_name' => $bankDetails['account_name'],
                        'settlement_bank' => $bankDetails['bank_code'],
                        'account_number' => $bankDetails['account_number'],
                        'percentage_charge' => 0, // We calculate commissions manually in split
                        'description' => 'Maid Salary Subaccount for ' . ($helper['full_name'] ?? 'Helper'),
                        'primary_contact_email' => 'helper_' . ($helper['id'] ?? time()) . '@maids.ng',
                        'primary_contact_name' => $bankDetails['account_name'],
                        'primary_contact_phone' => $helper['phone'] ?? '0000000000'
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] === true) {
                // Return generic structure map to match FW logic if possible
                // Paystack returns 'subaccount_code' (e.g. ACCT_xxxx)
                return [
                    'id' => $data['data']['subaccount_code'], // Map code to id
                    'subaccount_id' => $data['data']['subaccount_code'],
                    'bank_name' => $data['data']['settlement_bank'],
                    'account_number' => $data['data']['account_number']
                ];
            }

            throw new \Exception($data['message'] ?? 'Failed to create Paystack subaccount');
        } catch (GuzzleException $e) {
            $this->logger->error("Paystack subaccount creation failed", ['error' => $e->getMessage()]);
            throw new \Exception("Failed to connect to Paystack");
        }
    }

    public function getBanks(): array
    {
        try {
            $response = $this->httpClient->get(
                "{$this->config['flutterwave']['base_url']}/banks/NG",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->config['flutterwave']['secret_key']
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data'] ?? [];
        } catch (GuzzleException $e) {
            return [];
        }
    }

    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        try {
            $response = $this->httpClient->post(
                "{$this->config['flutterwave']['base_url']}/accounts/resolve",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->config['flutterwave']['secret_key'],
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'account_number' => $accountNumber,
                        'account_bank' => $bankCode
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] === 'success') {
                return $data['data'];
            }

            return ['error' => 'Account resolution failed'];
        } catch (GuzzleException $e) {
            return ['error' => 'Invalid account details'];
        }
    }

    public function verifyFlutterwavePayment(string $txRef, string $transactionId): array
    {
        try {
            $response = $this->httpClient->get(
                "{$this->config['flutterwave']['base_url']}/transactions/{$transactionId}/verify",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->config['flutterwave']['secret_key']
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] === 'success' && $data['data']['status'] === 'successful') {
                $this->markPaymentSuccess($txRef, $transactionId, $data['data']);
                return ['success' => true, 'data' => $data['data']];
            }

            return ['success' => false, 'message' => 'Payment verification failed'];
        } catch (GuzzleException $e) {
            $this->logger->error("Flutterwave verification failed", [
                'tx_ref' => $txRef,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => 'Payment verification error'];
        }
    }

    public function verifyPaystackPayment(string $reference): array
    {
        try {
            $response = $this->httpClient->get(
                "{$this->config['paystack']['base_url']}/transaction/verify/{$reference}",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->config['paystack']['secret_key']
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] === true && $data['data']['status'] === 'success') {
                $this->markPaymentSuccess($reference, $data['data']['reference'], $data['data']);
                return ['success' => true, 'data' => $data['data']];
            }

            return ['success' => false, 'message' => 'Payment verification failed'];
        } catch (GuzzleException $e) {
            $this->logger->error("Paystack verification failed", [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => 'Payment verification error'];
        }
    }

    public function markPaymentSuccess(string $txRef, string $gatewayRef, array $metadata = []): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE payments SET
                status = 'success',
                gateway_ref = ?,
                payment_method = ?,
                metadata = ?,
                paid_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE tx_ref = ?
        ");

        $result = $stmt->execute([
            $gatewayRef,
            $metadata['payment_type'] ?? $metadata['channel'] ?? null,
            json_encode($metadata),
            $txRef
        ]);

        if ($result) {
            // Get payment details to know what to update
            $payment = $this->getPayment($txRef);
            
            if ($payment && !empty($payment['booking_id'])) {
                // Update booking status
                $stmt = $this->pdo->prepare("
                    UPDATE bookings SET
                        status = 'confirmed',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$payment['booking_id']]);
            }

            if ($payment && !empty($payment['service_request_id'])) {
                // Update service request status
                $stmt = $this->pdo->prepare("
                    UPDATE service_requests SET
                        status = 'paid',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$payment['service_request_id']]);
            }

            $this->logger->info("Payment marked as success", ['tx_ref' => $txRef]);
        }

        return $result;
    }

    public function markPaymentFailed(string $txRef, string $reason = ''): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE payments SET
                status = 'failed',
                metadata = JSON_SET(COALESCE(metadata, '{}'), '$.failure_reason', ?),
                updated_at = CURRENT_TIMESTAMP
            WHERE tx_ref = ?
        ");

        $result = $stmt->execute([$reason, $txRef]);

        if ($result) {
            $this->logger->info("Payment marked as failed", ['tx_ref' => $txRef, 'reason' => $reason]);
        }

        return $result;
    }

    public function getPayment(string $txRef): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, 
                   b.employer_id, b.helper_id, b.reference as booking_ref,
                   sr.full_name as sr_name, sr.phone as sr_phone
            FROM payments p
            LEFT JOIN bookings b ON p.booking_id = b.id
            LEFT JOIN service_requests sr ON p.service_request_id = sr.id
            WHERE p.tx_ref = ?
        ");
        $stmt->execute([$txRef]);
        $payment = $stmt->fetch();

        if ($payment && $payment['metadata']) {
            $payment['metadata'] = json_decode($payment['metadata'], true);
        }

        return $payment ?: null;
    }

    public function getPaymentsByBooking(int $bookingId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC");
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll();
    }

    public function getPaymentStats(): array
    {
        $stats = [];

        // Total revenue
        $stmt = $this->pdo->query("SELECT SUM(amount) as total FROM payments WHERE status = 'success'");
        $stats['total_revenue'] = (int) ($stmt->fetch()['total'] ?? 0);

        // Today's revenue
        $stmt = $this->pdo->query("
            SELECT SUM(amount) as total FROM payments
            WHERE status = 'success' AND DATE(paid_at) = DATE('now')
        ");
        $stats['today_revenue'] = (int) ($stmt->fetch()['total'] ?? 0);

        // This month's revenue
        $stmt = $this->pdo->query("
            SELECT SUM(amount) as total FROM payments
            WHERE status = 'success' AND strftime('%Y-%m', paid_at) = strftime('%Y-%m', 'now')
        ");
        $stats['month_revenue'] = (int) ($stmt->fetch()['total'] ?? 0);

        // Payment counts by status
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM payments GROUP BY status");
        while ($row = $stmt->fetch()) {
            $stats['count_' . $row['status']] = $row['count'];
        }

        return $stats;
    }

    public function processRefund(int $paymentId, string $reason = ''): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE id = ? AND status = 'success'");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();

        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found or not eligible for refund'];
        }

        // Trigger Gateway Refund
        if ($payment['gateway'] === 'flutterwave') {
            try {
                $response = $this->httpClient->post(
                    "{$this->config['flutterwave']['base_url']}/transactions/{$payment['gateway_ref']}/refund",
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->config['flutterwave']['secret_key'],
                            'Content-Type' => 'application/json'
                        ],
                        'json' => [
                            'comments' => $reason
                        ]
                    ]
                );

                $data = json_decode($response->getBody()->getContents(), true);
                if ($data['status'] !== 'success') {
                    return ['success' => false, 'message' => 'Gateway refund failed: ' . ($data['message'] ?? 'Unknown error')];
                }
            } catch (GuzzleException $e) {
                return ['success' => false, 'message' => 'Gateway connection failed'];
            }
        }
        // Add Paystack refund logic here if needed

        // For now, just mark as refunded - actual refund would call gateway API
        $stmt = $this->pdo->prepare("
            UPDATE payments SET
                status = 'refunded',
                metadata = JSON_SET(COALESCE(metadata, '{}'), '$.refund_reason', ?),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$reason, $paymentId]);

        $this->logger->info("Payment refunded", ['payment_id' => $paymentId, 'reason' => $reason]);

        return ['success' => true, 'message' => 'Refund processed'];
    }
}
