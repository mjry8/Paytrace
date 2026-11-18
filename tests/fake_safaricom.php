<?php
// A pretend Safaricom for tests/run.php. Used with:  php -S 127.0.0.1:PORT tests/fake_safaricom.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = json_decode((string) file_get_contents('php://input'), true) ?: [];
header('Content-Type: application/json');
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($path === '/oauth/v1/generate') {
    if ($auth !== 'Basic ' . base64_encode('k:s')) { http_response_code(400); echo '{"errorMessage":"Bad credentials"}'; exit; }
    echo json_encode(['access_token' => 'TOKEN', 'expires_in' => '3599']); exit;
}
if ($auth !== 'Bearer TOKEN') { http_response_code(401); echo '{"errorMessage":"Invalid Access Token"}'; exit; }
if ($path === '/mpesa/stkpush/v1/processrequest') {
    $ok = is_int($body['Amount'] ?? null) && strlen($body['AccountReference'] ?? 'x') <= 12 && !empty($body['Password']);
    echo json_encode($ok ? ['ResponseCode' => '0', 'CheckoutRequestID' => 'ws_CO_TEST_1', 'ResponseDescription' => 'Success']
                         : ['ResponseCode' => '1', 'errorMessage' => 'Bad request']); exit;
}
if ($path === '/mpesa/stkpushquery/v1/query') {
    $id = $body['CheckoutRequestID'] ?? '';
    if ($id === 'busy') { http_response_code(500); echo '{"errorCode":"500.001.1001","errorMessage":"The transaction is being processed"}'; exit; }
    echo json_encode(['ResponseCode' => '0', 'ResultCode' => $id === 'cancelled' ? '1032' : '0', 'ResultDesc' => 'fake']); exit;
}
http_response_code(404); echo '{}';
