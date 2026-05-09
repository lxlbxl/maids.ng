<?php
/**
 * Maids.ng — Control Room Deploy
 * Upload to public/, visit: /deploy-control-room.php?token=setup-now
 * DELETE AFTER USE.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$token = $_GET['token'] ?? '';
if ($token !== 'setup-now') {
    http_response_code(403);
    die('<pre>Forbidden. Use ?token=setup-now</pre>');
}

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Maids.ng — Control Room Deploy</title>";
echo "<style>body{background:#0a0a0b;color:#e0e0e0;font:14px/1.6 monospace;padding:30px;max-width:900px;margin:0 auto}
h1{color:#2da48e;font-size:20px}h2{color:#fff;font-size:14px;margin-top:24px}
.ok{color:#4ade80}.warn{color:#facc15}.err{color:#ef4444}
.code{background:#121214;padding:2px 6px;border-radius:3px}
pre{background:#121214;padding:12px;border-radius:6px;overflow-x:auto;font-size:12px;margin:4px 0}
hr{border-color:#222;margin:20px 0}</style></head><body>";
echo "<h1>Maids.ng — Control Room Deploy</h1>";

$results = [];

function step(string $label, callable $fn): void {
    global $results;
    echo "<h2>$label</h2>";
    try {
        $out = $fn();
        echo "<span class='ok'>OK</span> — " . htmlspecialchars((string)$out) . "<br>";
        $results[] = ['step' => $label, 'status' => 'ok'];
    } catch (\Throwable $e) {
        echo "<span class='err'>FAILED</span> — " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        $results[] = ['step' => $label, 'status' => 'error', 'message' => $e->getMessage()];
    }
}

// ─────────────────────────────────────────────────────────────────────
// STEP 0: Clear stale cache files BEFORE Laravel boots
// ─────────────────────────────────────────────────────────────────────
step('Step 1: Clear Stale Caches', function () {
    $baseDir = dirname(__DIR__);
    $cacheDir = $baseDir . '/bootstrap/cache/';
    $deleted = 0;
    if (is_dir($cacheDir)) {
        foreach (glob($cacheDir . '*.php') as $file) {
            if (basename($file) !== '.gitignore') {
                @unlink($file);
                $deleted++;
            }
        }
    }
    return "Deleted $deleted cache files";
});

// ─────────────────────────────────────────────────────────────────────
// STEP 1: Bootstrap Laravel
// ─────────────────────────────────────────────────────────────────────
step('Step 2: Bootstrap Laravel', function () {
    $baseDir = dirname(__DIR__);
    require_once $baseDir . '/vendor/autoload.php';
    $app = require_once $baseDir . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $GLOBALS['_app']    = $app;
    $GLOBALS['_kernel'] = $kernel;
    return 'Laravel booted';
});
$app    = $GLOBALS['_app'];
$kernel = $GLOBALS['_kernel'];

// ─────────────────────────────────────────────────────────────────────
// STEP 2: Ensure migration tracking table exists, mark old ones as done
// ─────────────────────────────────────────────────────────────────────
step('Step 3: Setup Migration Tracking', function () use ($app) {
    $repo = $app->make('migration.repository');
    if (!$repo->repositoryExists()) {
        $repo->createRepository();

        // Mark migrations that already exist in the DB as "done".
        // We insert ALL migration files EXCEPT the 4 new Control Room ones.
        $newMigrations = [
            '2026_05_02_000001_create_agent_events_table',
            '2026_05_02_000002_create_human_task_queue_table',
            '2026_05_02_000003_create_agent_overrides_table',
            '2026_05_07_000001_create_agent_campaigns_social_tables',
        ];

        $files = glob(database_path('migrations') . '/*.php');
        sort($files);
        $connection = $app->make('db')->connection();
        $count = 0;
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $newMigrations)) {
                continue; // skip new ones — they should run fresh
            }
            $connection->table('migrations')->insert([
                'migration' => $name,
                'batch'     => 1,
            ]);
            $count++;
        }
        return "Created migrations table, marked $count existing migrations as done";
    }
    return 'Migrations table exists';
});

// ─────────────────────────────────────────────────────────────────────
// STEP 3: Run all missing agent-related migrations (Ambassador + Control Room)
// ─────────────────────────────────────────────────────────────────────
step('Step 4: Create Agent Tables', function () use ($app, $kernel) {
    $schema = $app->make('db')->connection()->getSchemaBuilder();
    $db     = $app->make('db')->connection();

    // Ordered by dependency: parent tables first, then children
    // Format: table => [migrationFile, skipIfExists]
    $migrations = [
        'agent_channel_identities'  => ['2026_05_03_210429_create_agent_channel_identities_table', true],
        'agent_prompt_templates'    => ['2026_05_01_000001_create_agent_prompt_templates_table', true],
        'agent_knowledge_base'      => ['2026_05_01_000002_create_agent_knowledge_base_table', true],
        'agent_conversations'       => ['2026_04_30_000007_create_agent_conversations_table', true],
        'agent_messages'            => ['2026_04_30_000008_create_agent_messages_table', true],
        'agent_leads'               => ['2026_04_30_000009_create_agent_leads_table', true],
        'agent_events'              => ['2026_05_02_000001_create_agent_events_table', true],
        'human_task_queue'          => ['2026_05_02_000002_create_human_task_queue_table', true],
        'agent_overrides'           => ['2026_05_02_000003_create_agent_overrides_table', true],
        'agent_campaigns'           => ['2026_05_07_000001_create_agent_campaigns_social_tables', true],
        // Fix migrations (always run — they ALTER existing tables)
        '_fix_channel_identities'   => ['2026_05_09_000001_fix_agent_channel_identities_columns', false],
        '_fix_conversations'        => ['2026_05_09_000002_fix_agent_conversations_columns', false],
        '_fix_messages'             => ['2026_05_09_000003_fix_agent_messages_columns', false],
        '_fix_leads'                => ['2026_05_09_000004_fix_agent_leads_columns', false],
    ];

    // Fetch already-run migrations from the DB
    $runMigrations = $db->table('migrations')->pluck('migration')->toArray();

    $created = [];
    foreach ($migrations as $table => $config) {
        [$migrationFile, $skipIfExists] = $config;

        // Skip if this migration was already recorded as run
        if (in_array($migrationFile, $runMigrations)) {
            continue;
        }

        // For the composite migration, check its first table
        $checkTable = $table === 'agent_campaigns' ? 'agent_campaigns' : $table;
        if ($skipIfExists && $schema->hasTable($checkTable)) {
            // Also mark it as run so we don't try again
            $db->table('migrations')->insertOrIgnore([
                'migration' => $migrationFile,
                'batch'     => 2,
            ]);
            continue;
        }

        $path = database_path("migrations/{$migrationFile}.php");
        if (!file_exists($path)) {
            throw new \Exception("Migration file not found: {$migrationFile}");
        }

        $migration = include $path;
        $migration->up();

        $db->table('migrations')->insertOrIgnore([
            'migration' => $migrationFile,
            'batch'     => 2,
        ]);

        $created[] = $table;
    }

    return $created ? 'Created: ' . implode(', ', $created) : 'Already exist — skipped';
});

// ─────────────────────────────────────────────────────────────────────
// STEP 4: Seed agent overrides
// ─────────────────────────────────────────────────────────────────────
step('Step 5: Seed Agent Overrides', function () use ($app, $kernel) {
    $schema = $app->make('db')->connection()->getSchemaBuilder();
    if (!$schema->hasTable('agent_overrides')) {
        throw new \Exception('agent_overrides table not found — step 4 must run first');
    }

    $count = $app->make('db')->connection()->table('agent_overrides')->count();
    if ($count > 0) {
        return "Already seeded ($count agents) — skipped";
    }

    $kernel->call('db:seed', ['--class' => 'AgentOverrideSeeder', '--force' => true]);
    $count = $app->make('db')->connection()->table('agent_overrides')->count();
    return "$count agent overrides seeded as active";
});

// ─────────────────────────────────────────────────────────────────────
// STEP 5b: Seed prompt templates & knowledge base
// ─────────────────────────────────────────────────────────────────────
step('Step 6: Seed Knowledge Base', function () use ($app, $kernel) {
    $schema = $app->make('db')->connection()->getSchemaBuilder();
    if (!$schema->hasTable('agent_prompt_templates') || !$schema->hasTable('agent_knowledge_base')) {
        return 'Tables missing — skipped';
    }

    $tplCount = $app->make('db')->connection()->table('agent_prompt_templates')->count();
    $kbCount  = $app->make('db')->connection()->table('agent_knowledge_base')->count();
    if ($tplCount > 0 && $kbCount > 0) {
        return "Already seeded ($tplCount templates, $kbCount articles) — skipped";
    }

    $kernel->call('db:seed', ['--class' => 'AgentKnowledgeSeeder', '--force' => true]);
    $tplCount = $app->make('db')->connection()->table('agent_prompt_templates')->count();
    $kbCount  = $app->make('db')->connection()->table('agent_knowledge_base')->count();
    return "$tplCount templates + $kbCount articles seeded";
});

// ─────────────────────────────────────────────────────────────────────
// STEP 6: Clear all application caches
// ─────────────────────────────────────────────────────────────────────
step('Step 7: Clear Application Caches', function () use ($kernel) {
    $kernel->call('route:clear');
    $kernel->call('config:clear');
    $kernel->call('view:clear');
    $kernel->call('cache:clear');
    return 'All caches cleared';
});

// ─────────────────────────────────────────────────────────────────────
// STEP 7: Verify
// ─────────────────────────────────────────────────────────────────────
step('Step 8: Verify Setup', function () use ($app) {
    $schema = $app->make('db')->connection()->getSchemaBuilder();

    $required = [
        'agent_channel_identities',
        'agent_conversations',
        'agent_messages',
        'agent_leads',
        'agent_prompt_templates',
        'agent_knowledge_base',
        'agent_events',
        'human_task_queue',
        'agent_overrides',
        'agent_campaigns',
        'social_posts',
    ];

    $missing = [];
    foreach ($required as $t) {
        if (!$schema->hasTable($t)) {
            $missing[] = $t;
        }
    }
    if ($missing) {
        throw new \Exception('Missing tables: ' . implode(', ', $missing));
    }

    $count = $app->make('db')->connection()->table('agent_overrides')->count();
    return "$count agents active — all tables verified";
});

// ─────────────────────────────────────────────────────────────────────
// Footer
// ─────────────────────────────────────────────────────────────────────
echo "<hr>";
$errors = count(array_filter($results, fn($r) => $r['status'] !== 'ok'));

if ($errors === 0) {
    echo "<span class='ok' style='font-size:16px'>All 8 steps passed!</span><br><br>";
    echo "Control Room: <a href='/admin/control-room' style='color:#2da48e'>/admin/control-room</a><br><br>";
    echo "<span class='warn'>IMPORTANT:</span> Delete this file:<br>";
    echo "<pre>rm public/deploy-control-room.php</pre>";
} else {
    echo "<span class='err' style='font-size:16px'>$errors step(s) failed. Fix errors and reload.</span><br>";
}
echo "<br><span style='color:#555'>Maids.ng Control Room v1.0</span></body></html>";
