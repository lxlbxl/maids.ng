<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use GuzzleHttp\Client;

$clientId = '71OATHED6SM44ZWIUQR3';
$clientSecret = '2a42c3f912dd44f8821518a01205ce89';

$client = new Client(['verify' => false]);

// Try standard OAuth endpoint first
$endpointsToTry = [
    'https://api.qoreid.com/token',
    'https://auth.qoreid.com/token',
    'https://auth.qoreid.com/auth/realms/qoreid/protocol/openid-connect/token'
];

foreach ($endpointsToTry as $url) {
    echo "Trying $url...\n";
    try {
        $response = $client->post($url, [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
        
        echo "SUCCESS on $url!\n";
        echo "Body: " . $response->getBody()->getContents() . "\n";
        exit;
    } catch (\Exception $e) {
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            echo "Failed: " . $e->getResponse()->getStatusCode() . " - " . $e->getResponse()->getBody()->getContents() . "\n";
        } else {
            echo "Failed: " . $e->getMessage() . "\n";
        }
    }
}
