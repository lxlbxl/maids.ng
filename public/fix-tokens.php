<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "<h1>Maids.ng Sanctum Table Fix</h1>";

try {
    if (!Schema::hasTable('personal_access_tokens')) {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        echo "<p style='color:green'>✅ Table 'personal_access_tokens' created successfully!</p>";
        echo "<p>API Token generation should now work.</p>";
    } else {
        echo "<p style='color:blue'>ℹ️ Table 'personal_access_tokens' already exists.</p>";
    }
} catch (\Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<p><b>IMPORTANT:</b> Delete this file (`public/fix-tokens.php`) after loading it once.</p>";
