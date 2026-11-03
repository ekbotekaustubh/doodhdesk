<?php
declare(strict_types=1);

function getDbConfig(): array
{
    return [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_DATABASE'] ?? 'dairyflow',
        'username' => $_ENV['DB_USERNAME'] ?? 'dairyflow',
        'password' => $_ENV['DB_PASSWORD'] ?? 'dairyflow123',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    ];
}

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = getDbConfig();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
        $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    }

    return $pdo;
}
