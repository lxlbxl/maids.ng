<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

class Connection
{
    private PDO $pdo;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    private function connect(): void
    {
        $driver = $this->config['driver'];

        try {
            if ($driver === 'sqlite') {
                $path = $this->config['sqlite']['path'];
                $dir = dirname($path);

                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $this->pdo = new PDO("sqlite:{$path}");
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $mysql = $this->config['mysql'];
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $mysql['host'],
                    $mysql['port'],
                    $mysql['database'],
                    $mysql['charset']
                );

                $this->pdo = new PDO(
                    $dsn,
                    $mysql['username'],
                    $mysql['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$mysql['charset']} COLLATE {$mysql['collation']}"
                    ]
                );
            }

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getDriver(): string
    {
        return $this->config['driver'];
    }

    public function isUsingSqlite(): bool
    {
        return $this->config['driver'] === 'sqlite';
    }
}
