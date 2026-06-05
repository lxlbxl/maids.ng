<?php
/**
 * Maids.ng — Control Room Deployment Script
 * 
 * Upload entire project to shared hosting, then visit:
 *   https://yourdomain.com/deploy-control-room.php?token=YOUR_DEPLOY_TOKEN
 * 
 * Uses the same token mechanism as the existing deploy routes.
 */

// ── Auth ──────────────────────────────────────────────────────────
$deploySecret = getenv('DEPLOY_SECRET') ?: 'setup-now';
$token = $_GET['token'] ?? '';

if ($token !== $deploySecret) {
    http_response_code(403);
    die("<pre style='color:red'>Access denied. Add ?token=YOUR_DEPLOY_TOKEN to the URL.</pre>");
}

// ── Bootstrap Laravel ─────────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// ── HTML Output ───────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Maids.ng — Deploy Control Room</title>";
echo "<style>body{background:#0a0a0b;color:#e0e0e0;font:14px/1.6 monospace;padding:30px;max-width:900px;margin:0 auto}
h1{color:#2da48e;font-size:20px}h2{color:#fff;font-size:14px;margin-top:24px}
.step{padding:6px 0}.ok{color:#4ade80}.warn{color:#facc15}.err{color:#ef4444}
.code{background:#121214;padding:2px 6px;border-radius:3px;font-size:12px}
pre{background:#121214;padding:12px;border-radius:6px;overflow-x:auto;font-size:12px;margin:4px 0}
hr{border-color:#222;margin:20px 0}</style></head><body>";
echo "<h1>Maids.ng — Control Room Deploy</h1>";

// ── Helper ────────────────────────────────────────────────────────
$results = [];

function runStep(string $label, callable $fn): void {
    global $results;
    echo "<h2>$label</h2>";
    try {
        $output = $fn();
        echo "<span class='ok'>OK</span> — " . htmlspecialchars($output) . "<br>";
        $results[] = ['step' => $label, 'status' => 'ok'];
    } catch (\Throwable $e) {
        echo "<span class='err'>FAILED</span> — " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        $results[] = ['step' => $label, 'status' => 'error', 'message' => $e->getMessage()];
    }
}

// ── Step 1: Migrate ───────────────────────────────────────────────
runStep('Step 1: Database Migrations', function () use ($kernel) {
    $kernel->call('migrate', ['--force' => true]);
    return 'All tables migrated successfully';
});

// ── Step 2: Seed ──────────────────────────────────────────────────
runStep('Step 2: Seed Agent Overrides', function () use ($kernel) {
    // Check if already seeded
    if (\App\Models\AgentOverride::count() > 0) {
        return 'Agent overrides already exist — skipped';
    }
    $kernel->call('db:seed', ['--class' => 'AgentOverrideSeeder', '--force' => true]);
    return '10 agent overrides seeded as active';
});

// ── Step 3: Clear Caches ──────────────────────────────────────────
runStep('Step 3: Clear Application Caches', function () use ($kernel) {
    $kernel->call('cache:clear');
    $kernel->call('config:clear');
    $kernel->call('route:clear');
    \Illuminate\Support\Facades\Cache::flush();
    return 'All caches cleared';
});

// ── Step 4: Verify Setup ──────────────────────────────────────────
runStep('Step 4: Verify Setup', function () {
    $tables = [
        'agent_events'    => \Illuminate\Support\Facades\Schema::hasTable('agent_events'),
        'human_task_queue'=> \Illuminate\Support\Facades\Schema::hasTable('human_task_queue'),
        'agent_overrides' => \Illuminate\Support\Facades\Schema::hasTable('agent_overrides'),
        'agent_campaigns' => \Illuminate\Support\Facades\Schema::hasTable('agent_campaigns'),
        'social_posts'    => \Illuminate\Support\Facades\Schema::hasTable('social_posts'),
    ];
    
    $allOk = true;
    foreach ($tables as $t => $exists) {
        $allOk = $allOk && $exists;
    }
    
    if (!$allOk) {
        throw new \Exception("Missing tables: " . implode(', ', array_keys(array_filter($tables, fn($v) => !$v))));
    }
    
    $overrideCount = \App\Models\AgentOverride::count();
    return "$overrideCount agent overrides active — tables verified";
});

// ── Footer ─────────────────────────────────────────────────────────
echo "<hr>";
$errors = count(array_filter($results, fn($r) => $r['status'] !== 'ok'));

if ($errors === 0) {
    echo "<span class='ok' style='font-size:16px'>All steps passed!</span><br><br>";
    echo "Control Room is ready at: <a href='/admin/control-room' class='code' style='color:#2da48e'>/admin/control-room</a><br><br>";
    echo "<span class='warn'>IMPORTANT:</span> Delete this file after deployment:<br>";
    echo "<pre>rm deploy-control-room.php</pre>";
} else {
    echo "<span class='err' style='font-size:16px'>$errors step(s) failed. Fix errors above and reload.</span><br>";
}

echo "<br><span style='color:#555'>Maids.ng Control Room v1.0</span>";
echo "</body></html>";
