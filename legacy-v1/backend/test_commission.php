<?php

// Test script to verify commission logic
ini_set('display_errors', 1);
error_reporting(E_ALL);

function calculateCommission($salary, $settings) {
    if ($settings['commission_type'] === 'fixed') {
        return $settings['commission_fixed_amount'];
    } else {
        return ($salary * $settings['commission_percent']) / 100;
    }
}

$salary = 50000;

// Test Case 1: Percentage
$settingsPercentage = [
    'commission_type' => 'percentage',
    'commission_percent' => 10,
    'commission_fixed_amount' => 5000,
];
$commission1 = calculateCommission($salary, $settingsPercentage);
$expected1 = 5000;

echo "Test 1 (Percentage): " . ($commission1 === $expected1 ? "PASSED" : "FAILED (Got $commission1, expected $expected1)") . "\n";

// Test Case 2: Fixed Fee
$settingsFixed = [
    'commission_type' => 'fixed',
    'commission_percent' => 10,
    'commission_fixed_amount' => 6000,
];
$commission2 = calculateCommission($salary, $settingsFixed);
$expected2 = 6000;

echo "Test 2 (Fixed Fee): " . ($commission2 === $expected2 ? "PASSED" : "FAILED (Got $commission2, expected $expected2)") . "\n";

?>
