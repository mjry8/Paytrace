<?php
declare(strict_types=1);
/**
 * One-time setup. Run from the project folder:   php tools/setup.php
 * Creates the database (if missing), the tables, and the first staff login.
 */
define('ROOT', dirname(__DIR__));
require ROOT . '/src/helpers.php';
require ROOT . '/src/Database.php';
require ROOT . '/src/Auth.php';

if (env('DB_DSN') === null) {                       // normal MySQL use: make the database first
    $name = env('DB_NAME', 'payguard');
    if (!preg_match('/^\w+$/', $name)) {
        exit("DB_NAME may only contain letters, numbers and underscores.\n");
    }
    try {
        $server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306')),
            env('DB_USER', 'root'), env('DB_PASS', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4");
    } catch (PDOException $err) {
        exit("Cannot reach MySQL: " . $err->getMessage() . "\nStart MySQL in the XAMPP Control Panel and check DB_* in .env\n");
    }
}
install_schema(db());
echo "Tables ready.\n";

$username = env('ADMIN_USERNAME', 'admin');
$exists = db()->prepare('SELECT 1 FROM users WHERE username = ?');
$exists->execute([$username]);
if ($exists->fetch()) {
    echo "Staff user '$username' already exists.\n";
} else {
    $password = env('ADMIN_PASSWORD') ?? bin2hex(random_bytes(5));
    create_user($username, $password, 'admin');
    echo "Created staff login:\n  username: $username\n  password: $password\n(Write it down. It is not shown again.)\n";
}
echo "Done. Open http://localhost/payguard/ in your browser.\n";
