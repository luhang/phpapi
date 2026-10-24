<?php

declare(strict_types=1);

/**
 * MySQL connection settings for the stock database.
 * Prefer environment variables; fall back to local defaults.
 */
return [
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => (int) (getenv('DB_PORT') ?: 3306),
    'dbname'   => getenv('DB_NAME') ?: 'stock1',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
];
