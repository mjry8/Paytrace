<?php
declare(strict_types=1);

/**
 * PayGuard - the ONE entry point. Every page is opened as index.php?page=NAME.
 * Read this file first: it shows the whole map of the site.
 */
define('ROOT', __DIR__);
require ROOT . '/src/helpers.php';
require ROOT . '/src/Database.php';
require ROOT . '/src/Rules.php';
require ROOT . '/src/Gateways.php';
require ROOT . '/src/Payments.php';
require ROOT . '/src/Auth.php';

// Safaricom posts payment results to  index.php/callback/<secret>
$path = $_SERVER['PATH_INFO'] ?? '';
if (str_starts_with($path, '/callback/')) {
    require ROOT . '/pages/callback.php';
    exit;
}

// page name => needs staff login?
const PAGES = [
    'home'           => false,   //Hello, welcome we Trace
    'checkout'       => false,   // customer pays
    'receipt'        => false,   // customer sees result and can report a problem
    'login'          => false,
    'logout'         => false,
    'dashboard'      => true,    // everything below is for staff
    'transactions'   => true,
    'incidents'      => true,
    'reconciliation' => true,
];

$page = query_param('page') ?: 'home';
if (!array_key_exists($page, PAGES)) {
    http_response_code(404);
    exit('Page not found.');
}
try {
    if (PAGES[$page]) {
        require_login();
    }
    require ROOT . "/pages/$page.php";
} catch (PDOException $err) {
    http_response_code(500);
    echo '<h2>Database problem</h2><p>Is MySQL running in XAMPP, and have you run <code>php tools/setup.php</code>? ';
    echo 'See docs/GUIDE.md.</p><pre>' . e($err->getMessage()) . '</pre>';
} catch (RuntimeException $err) {
    http_response_code(500);
    echo '<h2>Setup problem</h2><pre>' . e($err->getMessage()) . '</pre>';
}
