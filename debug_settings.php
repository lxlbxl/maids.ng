<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Setting;
use App\Services\Ai\AiService;

try {
    echo "Fetching settings...\n";
    $settings = Setting::all()->groupBy('group');
    echo "Settings count: " . count($settings) . "\n";

    echo "Initializing AiService...\n";
    $aiService = new AiService();
    
    echo "Fetching manifest...\n";
    $manifest = $aiService->getProviderManifest();
    echo "Manifest keys: " . implode(', ', array_keys($manifest)) . "\n";

    echo "SUCCESS\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
