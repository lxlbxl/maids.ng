<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Services\PaymentService;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Mock dependencies
$logger = new Logger('test');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$dbConfig = require __DIR__ . '/../config/database.php';
$connection = new Connection($dbConfig);
$pdo = $connection->getPdo();

$paymentConfig = require __DIR__ . '/../config/payments.php';

$paymentService = new PaymentService($pdo, $logger, $paymentConfig);

echo "Starting Payment Test...\n";

try {
    // 1. Setup Test Data
    $pdo->beginTransaction();

    // Create User (Helper)
    $phone = '080' . rand(10000000, 99999999);
    $pdo->exec("INSERT INTO users (phone, user_type) VALUES ('$phone', 'helper')");
    $helperUserId = $pdo->lastInsertId();

    // Create Helper Record
    $subaccountId = 'RS_' . uniqid();
    $stmt = $pdo->prepare("INSERT INTO helpers (user_id, full_name, work_type, location, subaccount_id, salary_min) VALUES (?, 'Test Helper', 'Maid', 'Lagos', ?, 50000)");
    $stmt->execute([$helperUserId, $subaccountId]);
    $helperId = $pdo->lastInsertId();
    echo "Created Helper ID: $helperId with Subaccount: $subaccountId\n";

    // Create User (Employer)
    $employerPhone = '080' . rand(10000000, 99999999);
    $pdo->exec("INSERT INTO users (phone, user_type) VALUES ('$employerPhone', 'employer')");
    $employerUserId = $pdo->lastInsertId();

    // Create Employer Record
    $pdo->exec("INSERT INTO employers (user_id, full_name) VALUES ($employerUserId, 'Test Employer')");
    $employerId = $pdo->lastInsertId();

    // Create Booking
    $ref = 'BK_' . uniqid();
    $monthlyRate = 60000;
    $stmt = $pdo->prepare("INSERT INTO bookings (reference, employer_id, helper_id, service_fee, monthly_rate, status) VALUES (?, ?, ?, 5000, ?, 'active')");
    $stmt->execute([$ref, $employerId, $helperId, $monthlyRate]);
    $bookingId = $pdo->lastInsertId();
    echo "Created Booking ID: $bookingId\n";

    // Set Commission to 10%
    $pdo->exec("INSERT OR REPLACE INTO settings (key_name, value, category) VALUES ('commission_percent', '10', 'payments')");

    // 2. Test Salary Payment Initialization
    echo "Initializing Salary Payment...\n";
    $result = $paymentService->createPayment($bookingId, 'flutterwave', 'salary');

    echo "Payment Created: ID " . $result['payment_id'] . "\n";
    echo "Amount: " . $result['amount'] . "\n";

    if (!isset($result['subaccounts']) || empty($result['subaccounts'])) {
        throw new Exception("Subaccounts missing in response");
    }

    $subaccount = $result['subaccounts'][0];
    echo "Subaccount ID: " . $subaccount['id'] . "\n";
    echo "Helper Amount: " . $subaccount['transaction_charge'] . "\n";

    // Verify math
    $commission = ($monthlyRate * 10) / 100;
    $expectedHelperAmount = $monthlyRate - $commission;

    if ($subaccount['transaction_charge'] != $expectedHelperAmount) {
        throw new Exception("Create Payment calculation failed. Expected $expectedHelperAmount, got " . $subaccount['transaction_charge']);
    }

    if ($subaccount['id'] !== $subaccountId) {
        throw new Exception("Subaccount ID mismatch");
    }

    echo "SUCCESS: Logic verified correctly!\n";

    $pdo->rollBack(); // Cleanup
    echo "Test data rolled back.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}
