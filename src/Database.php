<?php
declare(strict_types=1);

/** One shared connection to MySQL (settings come from .env). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    // DB_DSN is only used by the automatic tests (they run on SQLite). Normal use is MySQL.
    $dsn = env('DB_DSN') ?? sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        env('DB_NAME', 'payguard')
    );
    $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

/** Create the tables from database/schema.sql (does nothing for tables that already exist). */
function install_schema(PDO $pdo): void
{
    $sql = (string) file_get_contents(ROOT . '/database/schema.sql');
    $sql = preg_replace('/--[^\n]*/', '', $sql);                 // drop comments
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        // Only so the tests can run without a MySQL server: swap the few MySQL-only words.
        $sql = str_replace('AUTO_INCREMENT', 'AUTOINCREMENT', $sql);
        $sql = preg_replace('/\)\s*ENGINE=[^;]*;/', ');', $sql);
        $sql = preg_replace('/,\s*KEY\s+\w+\s*\([^)]*\)/', '', $sql);
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
}
