<?php
/**
 * Maids.ng Installation Wizard
 * 
 * This script automates the installation process on shared hosting.
 * Upload this file to your web root and access it via browser.
 * 
 * Requirements:
 * - PHP 8.2+
 * - MySQL 5.7+ or MariaDB 10.3+
 * - mod_rewrite enabled
 * - PDO PHP Extension
 * - OpenSSL PHP Extension
 * - Mbstring PHP Extension
 * - Tokenizer PHP Extension
 * - XML PHP Extension
 * - Ctype PHP Extension
 * - JSON PHP Extension
 * - BCMath PHP Extension
 */

session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Installation steps
$steps = [
    'welcome' => 'Welcome',
    'requirements' => 'System Requirements',
    'database' => 'Database Configuration',
    'app_config' => 'Application Configuration',
    'install' => 'Installation',
    'complete' => 'Complete'
];

$currentStep = $_GET['step'] ?? 'welcome';
$errors = [];
$success = [];

// Handle session messages
if (isset($_SESSION['success'])) {
    $success[] = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errors[] = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Helper functions
function checkPHPVersion(): bool
{
    return version_compare(PHP_VERSION, '8.2.0', '>=');
}

function checkExtension(string $extension): bool
{
    return extension_loaded($extension);
}

function checkWritable(string $path): bool
{
    return is_writable($path);
}

function generateRandomKey(int $length = 32): string
{
    return base64_encode(random_bytes($length));
}

function testDatabaseConnection(array $config): bool
{
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function createDatabaseTables(array $config): bool
{
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Read and execute SQL dump
        $sqlFile = __DIR__ . '/database/database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
        }

        return true;
    } catch (PDOException $e) {
        global $errors;
        $errors[] = "Database error: " . $e->getMessage();
        return false;
    }
}

function createAdminUser(array $dbConfig, array $appConfig): bool
{
    try {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Hash password using Laravel's default (Bcrypt)
        $hashedPassword = password_hash($appConfig['admin_password'], PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        // Check if user exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$appConfig['admin_email']]);
        $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            $userId = $existingUser['id'];
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $now, $userId]);
        } else {
            // Insert Admin User
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, status, created_at, updated_at) VALUES (?, ?, ?, 'active', ?, ?)");
            $stmt->execute(['Administrator', $appConfig['admin_email'], $hashedPassword, $now, $now]);
            $userId = $pdo->lastInsertId();
        }

        // Create Core Roles
        $roles = ['admin', 'maid', 'employer'];
        foreach ($roles as $roleName) {
            $pdo->prepare("INSERT IGNORE INTO roles (name, guard_name, created_at, updated_at) VALUES (?, 'web', ?, ?)")
                ->execute([$roleName, $now, $now]);
        }

        // Assign Admin Role
        $roleIdStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'admin' AND guard_name = 'web' LIMIT 1");
        $roleIdStmt->execute();
        $role = $roleIdStmt->fetch(PDO::FETCH_ASSOC);

        if ($role) {
            $pdo->prepare("INSERT IGNORE INTO model_has_roles (role_id, model_type, model_id) VALUES (?, 'App\\\\Models\\\\User', ?)")
                ->execute([$role['id'], $userId]);
        }

        return true;
    } catch (PDOException $e) {
        global $errors;
        $errors[] = "Admin creation failed: " . $e->getMessage();
        return false;
    }
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($currentStep) {
        case 'database':
            $dbConfig = [
                'host' => $_POST['db_host'] ?? 'localhost',
                'port' => $_POST['db_port'] ?? '3306',
                'database' => $_POST['db_name'] ?? '',
                'username' => $_POST['db_user'] ?? '',
                'password' => $_POST['db_pass'] ?? ''
            ];

            if (testDatabaseConnection($dbConfig)) {
                $_SESSION['db_config'] = $dbConfig;
                $_SESSION['success'] = "Database connection successful!";
                header("Location: ?step=app_config");
                exit;
            } else {
                $errors[] = "Could not connect to database. Please check your credentials.";
            }
            break;

        case 'app_config':
            $_SESSION['app_config'] = [
                'app_name' => $_POST['app_name'] ?? 'Maids.ng',
                'app_url' => $_POST['app_url'] ?? '',
                'app_env' => $_POST['app_env'] ?? 'production',
                'admin_email' => $_POST['admin_email'] ?? '',
                'admin_password' => $_POST['admin_password'] ?? ''
            ];
            header("Location: ?step=install");
            exit;
            break;

        case 'install':
            // Perform installation
            $installed = true;

            // 1. Create .env file
            $envContent = generateEnvFile($_SESSION['db_config'], $_SESSION['app_config']);
            if (!file_put_contents(__DIR__ . '/.env', $envContent)) {
                $errors[] = "Could not create .env file. Please check file permissions.";
                $installed = false;
            }

            // 2. Create database tables
            if ($installed && !createDatabaseTables($_SESSION['db_config'])) {
                $installed = false;
            }

            // 3. Create admin user
            if ($installed && !createAdminUser($_SESSION['db_config'], $_SESSION['app_config'])) {
                $installed = false;
            }

            // 3. Create storage directories
            if ($installed) {
                $dirs = [
                    'storage/app',
                    'storage/framework/cache',
                    'storage/framework/sessions',
                    'storage/framework/views',
                    'storage/logs',
                    'bootstrap/cache'
                ];

                foreach ($dirs as $dir) {
                    $path = __DIR__ . '/' . $dir;
                    if (!is_dir($path)) {
                        mkdir($path, 0755, true);
                    }
                }
            }

            // 4. Set permissions
            if ($installed) {
                chmod(__DIR__ . '/storage', 0755);
                chmod(__DIR__ . '/bootstrap/cache', 0755);

                // Create storage link
                if (!file_exists(__DIR__ . '/public/storage')) {
                    @symlink(__DIR__ . '/storage/app/public', __DIR__ . '/public/storage');
                }
            }

            // 5. Create .htaccess files for shared hosting
            if ($installed) {
                $rootHtaccess = "<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteRule ^(.*)$ public/$1 [L]\n</IfModule>";
                if (!file_exists(__DIR__ . '/.htaccess')) {
                    file_put_contents(__DIR__ . '/.htaccess', $rootHtaccess);
                }

                $publicHtaccess = "<IfModule mod_rewrite.c>\n    <IfModule mod_negotiation.c>\n        Options -MultiViews -Index\n    </IfModule>\n\n    RewriteEngine On\n\n    RewriteCond %{HTTP:Authorization} .\n    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP_AUTHORIZATION}]\n\n    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteCond %{REQUEST_URI} (.+)/$\n    RewriteRule ^ %1 [L,R=301]\n\n    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteCond %{REQUEST_FILENAME} !-f\n    RewriteRule ^ index.php [L]\n</IfModule>";
                if (!file_exists(__DIR__ . '/public/.htaccess')) {
                    file_put_contents(__DIR__ . '/public/.htaccess', $publicHtaccess);
                }
            }

            if ($installed) {
                $_SESSION['installed'] = true;
                $_SESSION['success'] = "Installation completed successfully!";
                header("Location: ?step=complete");
                exit;
            }
            break;
    }
}

function generateEnvFile(array $dbConfig, array $appConfig): string
{
    $appKey = 'base64:' . generateRandomKey(32);

    return <<<ENV
APP_NAME="{$appConfig['app_name']}"
APP_ENV={$appConfig['app_env']}
APP_KEY={$appKey}
APP_DEBUG=false
APP_URL={$appConfig['app_url']}

LOG_CHANNEL=daily
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST={$dbConfig['host']}
DB_PORT={$dbConfig['port']}
DB_DATABASE={$dbConfig['database']}
DB_USERNAME={$dbConfig['username']}
DB_PASSWORD={$dbConfig['password']}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@maids.ng"
MAIL_FROM_NAME="{$appConfig['app_name']}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="\${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="\${PUSHER_HOST}"
VITE_PUSHER_PORT="\${PUSHER_PORT}"
VITE_PUSHER_SCHEME="\${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="\${PUSHER_APP_CLUSTER}"

# Payment Gateways
PAYSTACK_PUBLIC_KEY=
PAYSTACK_SECRET_KEY=
PAYSTACK_PAYMENT_URL=https://api.paystack.co
MERCHANT_EMAIL=

FLUTTERWAVE_PUBLIC_KEY=
FLUTTERWAVE_SECRET_KEY=
FLUTTERWAVE_ENCRYPTION_KEY=
FLUTTERWAVE_PAYMENT_URL=https://api.flutterwave.com/v3

# AI Services
OPENROUTER_API_KEY=
OPENAI_API_KEY=
AI_PROVIDER=openrouter

# Verification Services
NIN_VERIFICATION_ENABLED=false
NIN_API_KEY=
NIN_API_URL=
BACKGROUND_CHECK_ENABLED=false
BACKGROUND_CHECK_API_KEY=
ENV;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maids.ng Installation Wizard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
        }

        .progress-bar {
            display: flex;
            background: #f3f4f6;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .progress-bar li {
            flex: 1;
            text-align: center;
            padding: 15px 10px;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            position: relative;
        }

        .progress-bar li.active {
            background: #667eea;
            color: white;
        }

        .progress-bar li.completed {
            background: #10b981;
            color: white;
        }

        .content {
            padding: 40px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6b7280;
            margin-left: 10px;
        }

        .requirement-list {
            list-style: none;
        }

        .requirement-list li {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .requirement-list li:last-child {
            border-bottom: none;
        }

        .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pass {
            background: #d1fae5;
            color: #065f46;
        }

        .status-fail {
            background: #fee2e2;
            color: #991b1b;
        }

        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
        }

        .info-box h3 {
            color: #1e40af;
            margin-bottom: 10px;
        }

        .info-box p {
            color: #1e3a8a;
            line-height: 1.6;
        }

        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 600px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🏠 Maids.ng Installation</h1>
            <p>Setup wizard for shared hosting deployment</p>
        </div>

        <ul class="progress-bar">
            <?php
            $stepReached = false;
            foreach ($steps as $key => $label):
                $class = '';
                if ($key === $currentStep) {
                    $class = 'active';
                    $stepReached = true;
                } elseif (!$stepReached) {
                    $class = 'completed';
                }
                ?>
                <li class="<?php echo $class; ?>"><?php echo $label; ?></li>
            <?php endforeach; ?>
        </ul>

        <div class="content">
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <?php foreach ($success as $msg): ?>
                    <div class="alert alert-success">✅ <?php echo htmlspecialchars($msg); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php switch ($currentStep):
                case 'welcome': ?>
                    <div class="info-box">
                        <h3>Welcome to Maids.ng!</h3>
                        <p>This wizard will guide you through the installation process on your shared hosting. Please ensure you
                            have:</p>
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            <li>MySQL database credentials</li>
                            <li>FTP/cPanel access to upload files</li>
                            <li>PHP 8.2 or higher</li>
                        </ul>
                    </div>

                    <p style="margin-bottom: 20px;">The installation process will:</p>
                    <ol style="margin-left: 20px; line-height: 2;">
                        <li>Check system requirements</li>
                        <li>Configure database connection</li>
                        <li>Set up application settings</li>
                        <li>Create database tables</li>
                        <li>Configure file permissions</li>
                    </ol>

                    <form method="get" style="margin-top: 30px;">
                        <input type="hidden" name="step" value="requirements">
                        <button type="submit" class="btn">Start Installation →</button>
                    </form>
                    <?php break; ?>

                <?php case 'requirements':
                    $requirements = [
                        'PHP 8.2+' => checkPHPVersion(),
                        'PDO Extension' => checkExtension('pdo'),
                        'PDO MySQL' => checkExtension('pdo_mysql'),
                        'OpenSSL' => checkExtension('openssl'),
                        'Mbstring' => checkExtension('mbstring'),
                        'Tokenizer' => checkExtension('tokenizer'),
                        'XML' => checkExtension('xml'),
                        'Ctype' => checkExtension('ctype'),
                        'JSON' => checkExtension('json'),
                        'BCMath' => checkExtension('bcmath'),
                        'Fileinfo' => checkExtension('fileinfo'),
                    ];

                    $writable = [
                        'storage/' => checkWritable(__DIR__ . '/storage'),
                        'bootstrap/cache/' => checkWritable(__DIR__ . '/bootstrap/cache'),
                        'public/' => checkWritable(__DIR__ . '/public'),
                    ];

                    $allPassed = !in_array(false, $requirements, true) && !in_array(false, $writable, true);
                    ?>
                    <h2 style="margin-bottom: 20px;">System Requirements</h2>

                    <h3 style="margin: 20px 0 10px;">PHP Extensions</h3>
                    <ul class="requirement-list">
                        <?php foreach ($requirements as $name => $passed): ?>
                            <li>
                                <span><?php echo $name; ?></span>
                                <span class="status <?php echo $passed ? 'status-pass' : 'status-fail'; ?>">
                                    <?php echo $passed ? '✓ Pass' : '✗ Fail'; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <h3 style="margin: 20px 0 10px;">Directory Permissions</h3>
                    <ul class="requirement-list">
                        <?php foreach ($writable as $name => $passed): ?>
                            <li>
                                <span><?php echo $name; ?></span>
                                <span class="status <?php echo $passed ? 'status-pass' : 'status-fail'; ?>">
                                    <?php echo $passed ? '✓ Writable' : '✗ Not Writable'; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($allPassed): ?>
                        <div class="alert alert-success" style="margin-top: 20px;">
                            ✅ All requirements met! You can proceed with the installation.
                        </div>
                        <form method="get" style="margin-top: 20px;">
                            <input type="hidden" name="step" value="database">
                            <button type="submit" class="btn">Continue →</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-error" style="margin-top: 20px;">
                            ❌ Some requirements are not met. Please fix the issues above and refresh this page.
                        </div>
                    <?php endif; ?>
                    <?php break; ?>

                <?php case 'database': ?>
                    <h2 style="margin-bottom: 20px;">Database Configuration</h2>

                    <div class="info-box">
                        <h3>Database Setup</h3>
                        <p>Please create a MySQL database and user in your hosting control panel (cPanel/DirectAdmin) before
                            proceeding. Enter the credentials below.</p>
                    </div>

                    <form method="post">
                        <div class="two-column">
                            <div class="form-group">
                                <label for="db_host">Database Host</label>
                                <input type="text" id="db_host" name="db_host" value="localhost" required>
                            </div>

                            <div class="form-group">
                                <label for="db_port">Port</label>
                                <input type="number" id="db_port" name="db_port" value="3306" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="db_name">Database Name</label>
                            <input type="text" id="db_name" name="db_name" required>
                        </div>

                        <div class="form-group">
                            <label for="db_user">Database Username</label>
                            <input type="text" id="db_user" name="db_user" required>
                        </div>

                        <div class="form-group">
                            <label for="db_pass">Database Password</label>
                            <input type="password" id="db_pass" name="db_pass" required>
                        </div>

                        <button type="submit" class="btn">Test & Continue →</button>
                        <a href="?step=requirements" class="btn btn-secondary">← Back</a>
                    </form>
                    <?php break; ?>

                <?php case 'app_config': ?>
                    <h2 style="margin-bottom: 20px;">Application Configuration</h2>

                    <form method="post">
                        <div class="form-group">
                            <label for="app_name">Application Name</label>
                            <input type="text" id="app_name" name="app_name" value="Maids.ng" required>
                        </div>

                        <div class="form-group">
                            <label for="app_url">Application URL</label>
                            <input type="text" id="app_url" name="app_url" placeholder="https://yourdomain.com" required>
                            <small style="color: #6b7280;">Include https:// and no trailing slash</small>
                        </div>

                        <div class="form-group">
                            <label for="app_env">Environment</label>
                            <select id="app_env" name="app_env">
                                <option value="production">Production</option>
                                <option value="local">Development</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="admin_email">Admin Email</label>
                            <input type="email" id="admin_email" name="admin_email" required>
                        </div>

                        <div class="form-group">
                            <label for="admin_password">Admin Password</label>
                            <input type="password" id="admin_password" name="admin_password" minlength="8" required>
                            <small style="color: #6b7280;">Minimum 8 characters</small>
                        </div>

                        <button type="submit" class="btn">Continue →</button>
                        <a href="?step=database" class="btn btn-secondary">← Back</a>
                    </form>
                    <?php break; ?>

                <?php case 'install': ?>
                    <h2 style="margin-bottom: 20px;">Ready to Install</h2>

                    <div class="info-box">
                        <h3>Installation Summary</h3>
                        <p>Click the button below to start the installation. This will:</p>
                        <ul style="margin-top: 10px; margin-left: 20px; line-height: 1.8;">
                            <li>Create the <code>.env</code> configuration file</li>
                            <li>Set up database tables</li>
                            <li>Create required directories</li>
                            <li>Set proper file permissions</li>
                        </ul>
                    </div>

                    <form method="post">
                        <button type="submit" class="btn" style="font-size: 18px; padding: 15px 40px;">
                            🚀 Start Installation
                        </button>
                        <a href="?step=app_config" class="btn btn-secondary">← Back</a>
                    </form>
                    <?php break; ?>

                <?php case 'complete': ?>
                    <div style="text-align: center; padding: 40px 0;">
                        <div style="font-size: 80px; margin-bottom: 20px;">🎉</div>
                        <h2 style="margin-bottom: 20px;">Installation Complete!</h2>
                        <p style="font-size: 18px; color: #6b7280; margin-bottom: 30px;">
                            Maids.ng has been successfully installed on your server.
                        </p>
                    </div>

                    <div class="info-box">
                        <h3>Next Steps</h3>
                        <ol style="margin-top: 10px; margin-left: 20px; line-height: 2;">
                            <li><strong>Delete install.php</strong> for security: <code>rm install.php</code></li>
                            <li><strong>Access your site:</strong> <a href="/" target="_blank">Visit Homepage</a></li>
                            <li><strong>Admin login:</strong> Use the email and password you configured</li>
                            <li><strong>Configure payments:</strong> Add your Paystack/Flutterwave keys in admin settings</li>
                            <li><strong>Set up email:</strong> Configure SMTP in admin settings for notifications</li>
                        </ol>
                    </div>

                    <div class="alert alert-success" style="margin-top: 20px;">
                        <strong>Important:</strong> For security reasons, please delete the install.php file immediately!
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <a href="/" class="btn">Go to Homepage →</a>
                        <a href="/admin" class="btn btn-secondary">Go to Admin →</a>
                    </div>
                    <?php break; ?>
            <?php endswitch; ?>
        </div>
    </div>
</body>

</html>