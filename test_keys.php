<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "FLW Public Key: " . \App\Models\Setting::get('flutterwave_public_key') . "\n";
echo "FLW Secret Key: " . \App\Models\Setting::get('flutterwave_secret_key') . "\n";
echo "Config FLW Public Key: " . config('services.flutterwave.public_key') . "\n";
echo "Config FLW Secret Key: " . config('services.flutterwave.secret_key') . "\n";
