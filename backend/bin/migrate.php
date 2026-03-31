<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Database\Migration;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Get database config
$config = require __DIR__ . '/../config/database.php';

// Create connection
$connection = new Connection($config);
$pdo = $connection->getPdo();

// Run migrations
$migration = new Migration($pdo, $connection->isUsingSqlite());

echo "Running migrations...\n";
$migration->run();
echo "Migrations complete!\n";
