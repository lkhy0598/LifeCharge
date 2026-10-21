<?php

declare(strict_types=1);

function connectDatabase(): PDO
{
    $configPath = dirname(__DIR__) . '/config/database.local.php';

    if (!is_file($configPath)) {
        throw new RuntimeException('Database configuration is missing.');
    }

    $config = require $configPath;

    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $config['host'],
        $config['port'],
        $config['charset']
    );

    if ($config['database'] !== '') {
        $dsn .= ';dbname=' . $config['database'];
    }

    return new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 2,
        ]
    );
}