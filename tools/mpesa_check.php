<?php
declare(strict_types=1);
/**
 * Check your Daraja settings WITHOUT touching the database:   php tools/mpesa_check.php
 * Add a phone number to send a real STK Push of KES 1:        php tools/mpesa_check.php 254712345678
 */
define('ROOT', dirname(__DIR__));
require ROOT . '/src/helpers.php';
require ROOT . '/src/Gateways.php';

try {
    $gw = DarajaGateway::fromEnv();
    echo "Settings found.\n";
    $phone = $argv[1] ?? null;
    if ($phone === null) {
        $answer = $gw->query(['gateway_request_id' => 'ws_CO_check_only']);   // forces login to Daraja
        echo "Daraja accepted your keys and replied: " . json_encode($answer) . "\n";
        echo "To send a test prompt: php tools/mpesa_check.php 254712345678\n";
    } else {
        $answer = $gw->push(['reference' => 'CHECK' . random_int(100, 999), 'amount' => 1], $phone);
        echo "Result: " . json_encode($answer) . "\nNow look at your phone.\n";
    }
} catch (Throwable $err) {
    exit("Problem: " . $err->getMessage() . "\n");
}
