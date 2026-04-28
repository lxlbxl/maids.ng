<?php

// Test script to verify preMatchNin endpoint controller and service without frontend
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Let's test the endpoint locally via curl since we don't have Slim container easily accessible here
$data = [
    'nin' => '12345678901',
    'firstName' => 'John',
    'lastName' => 'Doe'
];

$url = 'http://localhost:8000/api/verify-nin-prematch';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

// This is just to test how the endpoint behaves.
// Ensure your backend server is running when you run this!
echo "Run this with backend server running: php test_pre_match_nin.php\n";
// $response = curl_exec($ch);
// echo $response . "\n";
// curl_close($ch);
