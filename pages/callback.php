<?php
// Safaricom posts the result of an STK Push here. The secret token in the address is the lock.
// Always answer 200 once the token is right, so Safaricom does not keep retrying.
$expected = env('MPESA_CALLBACK_TOKEN');
$given = substr((string) ($_SERVER['PATH_INFO'] ?? ''), strlen('/callback/'));
if ($expected === null || !hash_equals($expected, $given)) {
    http_response_code(404);
    exit('Not found');
}
header('Content-Type: application/json');
$parsed = DarajaGateway::parseCallback((array) json_decode((string) file_get_contents('php://input'), true));
if ($parsed !== null) {
    try {
        handle_callback($parsed);
    } catch (Throwable $err) {
        error_log('PayGuard callback error: ' . $err->getMessage());   // we still answer 200
    }
}
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
