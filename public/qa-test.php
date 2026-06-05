<?php

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Controllers\Api\AdminController;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Request::capture());

echo "Starting QA Test...\n";

// Find an admin user
$admin = User::where('role', 'admin')->first();
if (!$admin) {
    die("No admin user found.\n");
}
Auth::login($admin);
echo "Logged in as Admin: {$admin->email}\n";

$controller = app(AdminController::class);

echo "\n--- Testing Dashboard Stats ---\n";
try {
    $response = $controller->dashboard();
    echo "Status: " . $response->status() . "\n";
    if ($response->status() == 200) {
        echo "Dashboard Stats returned successfully.\n";
    } else {
        echo "Error: " . $response->getContent() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "\n--- Testing Pending Withdrawals ---\n";
try {
    $request = Request::create('/api/v1/admin/withdrawals/pending', 'GET');
    $response = $controller->pendingWithdrawals($request);
    echo "Status: " . $response->status() . "\n";
    if ($response->status() == 200) {
        echo "Pending Withdrawals returned successfully.\n";
    } else {
        echo "Error: " . $response->getContent() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "\n--- Testing Salary Schedules ---\n";
try {
    $request = Request::create('/api/v1/admin/salary/schedules', 'GET');
    $response = $controller->salarySchedules($request);
    echo "Status: " . $response->status() . "\n";
    if ($response->status() == 200) {
        echo "Salary Schedules returned successfully.\n";
    } else {
        echo "Error: " . $response->getContent() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "\nQA Test Completed.\n";
