<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

$token = 'eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICIzaVgtaEFrS3RmNUlsYWhRcElrNWwwbFBRVlNmVnpBdG9WVWQ4UXZ1OHJFIn0.eyJleHAiOjE3Nzc5Mjg0NTcsImlhdCI6MTc3NzkyMTI1NywianRpIjoiMzcxZjQ5ZDItNjE4OC00YTU0LWE4MmItM2QxNjY4NDY0NDZiIiwiaXNzIjoiaHR0cHM6Ly9hdXRoLnFvcmVpZC5jb20vYXV0aC9yZWFsbXMvcW9yZWlkIiwiYXVkIjpbInFvcmVpZGFwaSIsImFjY291bnQiXSwic3ViIjoiZGEwYzgxNWItY2Q3Yi00OWI5LWE5NjEtNGQyNzIwNzZjYjc2IiwidHlwIjoiQmVhcmVyIiwiYXpwIjoiNzFPQVRIRUQ2U000NFpXSVVRUjMiLCJhY3IiOiIxIiwicmVhbG1fYWNjZXNzIjp7InJvbGVzIjpbIm9mZmxpbmVfYWNjZXNzIiwidW1hX2F1dGhvcml6YXRpb24iLCJkZWZhdWx0LXJvbGVzLXFvcmVpZCJdfSwicmVzb3VyY2VfYWNjZXNzIjp7InFvcmVpZGFwaSI6eyJyb2xlcyI6WyJ2ZXJpZnlfbGl2ZW5lc3Nfb2NyX3N1YiIsInZlcmlmeV9uaW5fcHJlbWl1bV9zdWIiLCJ2ZXJpZnlfdGluX3N1YiJdfSwiYWNjb3VudCI6eyJyb2xlcyI6WyJtYW5hZ2UtYWNjb3VudCIsIm1hbmFnZS1hY2NvdW50LWxpbmtzIiwidmlldy1wcm9maWxlIl19fSwic2NvcGUiOiJwcm9maWxlIGVtYWlsIiwiZW52aXJvbm1lbnQiOiJsaXZlIiwib3JnYW5pc2F0aW9uSWQiOjI1MjIwMywiY2xpZW50SG9zdCI6IjMuMjUzLjI1MS4xMiIsImNsaWVudElkIjoiNzFPQVRIRUQ2U000NFpXSVVRUjMiLCJlbWFpbF92ZXJpZmllZCI6ZmFsc2UsInByZWZlcnJlZF91c2VybmFtZSI6InNlcnZpY2UtYWNjb3VudC03MW9hdGhlZDZzbTQ0endpdXFyMyIsImFwcGxpY2F0aW9uSWQiOjI2Mjg4LCJjbGllbnRBZGRyZXNzIjoiMy4yNTMuMjUxLjEyIn0.SrT8_w0hu0GYn8k1OIHoFZZjCPhcdqGEuGOZCvlPaBX30GgWGjbcjmVk0vJwPFTs77Pc0C_q2bNUYrR-D7a4UEYDahiu-fj-neuaL8rjOpFMYJ0Bm5jM4Tau9gcyyL9RJIi42IyVoRqs4d2XKgAi43C45rYuk-N8Kx9iLCKp6QHdi9T0jQ3HpxQERkgsg-OLIgnjBS-E_N3MKn3Ga6Wu5gAnKzz_6awOuzoNMWTZzN2TmNAyubDRm5yIJYkp3UJQNH56En7H0kg-feW5iqNU-WgvRI17ZNGVXuu37OA9t3LuC7sBHCZkDiYrJUQH-AJiWGvRJCg2HeqbMrO-4DBWCA';
$nin = '21942809895';
$baseUrl = 'https://api.qoreid.com/v1';

$client = new Client();
$endpoint = "{$baseUrl}/ng/identities/nin-premium/" . urlencode($nin);

$body = [
    'firstname' => 'Test',
    'lastname' => 'User',
];

try {
    echo "Endpoint: $endpoint\n";
    $response = $client->post($endpoint, [
        'verify' => false,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'json' => $body,
    ]);
    
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Body: " . $response->getBody()->getContents() . "\n";
} catch (GuzzleException $e) {
    if ($e->hasResponse()) {
        echo "Status: " . $e->getResponse()->getStatusCode() . "\n";
        echo "Error Body: " . $e->getResponse()->getBody()->getContents() . "\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
