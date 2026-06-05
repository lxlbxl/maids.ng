<?php

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Controllers\Api\AdminController;
use Illuminate\Http\Request;

echo "Starting QA Test...\n";

// Find an admin user using Spatie Permission
$admin = User::role('admin')->first();
if (!$admin) {
    // Try role column as fallback
    $admin = User::where('role', 'admin')->first();
}
if (!$admin) {
    // Just find the first user and assign admin role
    $admin = User::first();
    if ($admin) {
        $admin->assignRole('admin');
    } else {
        die("No users found in database.\n");
    }
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
