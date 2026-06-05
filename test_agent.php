<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $agent = app(\App\Services\Agents\AmbassadorAgent::class);
    $msg = \App\Services\Agents\DTOs\InboundMessage::fromWeb([
        'message' => 'Hello', 
        'session_id' => '123', 
        'message_id' => 'abc'
    ]);
    print_r($agent->handle($msg));
} catch (\Throwable $e) {
    echo "CAUGHT ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
