<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\QoreIDService;

// Override config
config(['services.qoreid.client_id' => '7F3GS9ZMJQ26G5F7OYG2']);
config(['services.qoreid.client_secret' => '4a53c627c6984083ad2d9ac0e932ff8b']);

$service = app(QoreIDService::class);

$nin = '63184876213';
$firstName = 'Bunch';
$lastName = 'Dillon';

echo "Testing NIN Verification...\n";

$result = $service->verifyNinPremium($nin, $firstName, $lastName);

echo "\n--- Raw verifyNinPremium Result ---\n";
print_r($result);

echo "\n--- Testing compareNames ---\n";
if (isset($result['data'])) {
    $qoreData = $result['data'];
    $identityData = $qoreData['nin'] ?? $qoreData['nin_premium'] ?? $qoreData['data'] ?? $qoreData;
    
    echo "Extracted Identity Data Keys:\n";
    print_r(array_keys($identityData));

    $comparison = $service->compareNames($firstName, $lastName, $qoreData);
    print_r($comparison);
} else {
    echo "No data returned.\n";
}
