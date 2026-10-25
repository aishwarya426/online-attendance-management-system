<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

// Database settings. Use 127.0.0.1 rather than "localhost": with "localhost" the
// MySQL driver talks over a Unix socket and silently ignores the port.
const DB_HOST = '127.0.0.1';
const DB_PORT = 3307;
const DB_NAME = 'attsystem';
const DB_USER = 'root';
const DB_PASS = '';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $ex) {
    error_log('Database connection failed: ' . $ex->getMessage());
    http_response_code(500);
    exit('Cannot connect to the database. Check that MySQL is running on port ' . DB_PORT . ' and that the "' . DB_NAME . '" database exists.');
}

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/layout.php';
