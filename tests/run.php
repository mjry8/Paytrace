<?php
declare(strict_types=1);
/**
 * The automatic tests:   php tests/run.php
 * They use a throw-away SQLite file (no MySQL needed) and a pretend Safaricom running on your own computer.
 * They check the LOGIC. They do not prove MySQL or the real Safaricom work: do the checks in docs/GUIDE.md for that.
 */
define('ROOT', dirname(__DIR__));
$dbFile = sys_get_temp_dir() . '/payguard_test_' . getmypid() . '.db';
putenv('DB_DSN=sqlite:' . $dbFile);
putenv('USE_SIMULATOR=true');
putenv('MPESA_CALLBACK_TOKEN=testtoken');
foreach (['helpers', 'Database', 'Rules', 'Gateways', 'Payments', 'Auth'] as $f) {
    require ROOT . "/src/$f.php";
}

$failed = 0;
function check(string $name, bool $ok): void
{
    global $failed;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $name . "\n";
    $failed += $ok ? 0 : 1;
}
function status(int $id): string { return find_transaction($id)['status']; }
function pay(string $scenario, string $amount = '1000'): int { return start_payment('Test', '254712345678', 'Shoes', $amount, $scenario); }
function count_of(string $sql): int { return (int) db()->query($sql)->fetchColumn(); }

install_schema(db());

echo "Rules\n";
$p = ['status' => 'PENDING', 'amount' => '100.00', 'gateway_message' => null, 'mpesa_receipt' => null];
check('success updates', apply_gateway_result($p, 'SUCCESS', 'ok', 'R1', 100.0)['status'] === 'SUCCESS');
check('repeat callback ignored', apply_gateway_result(['status' => 'SUCCESS'] + $p, 'FAILED', 'x')['outcome'] === 'ignored');
check('amount mismatch flagged', apply_gateway_result($p, 'SUCCESS', 'ok', 'R1', 50.0)['status'] === 'FLAGGED');
check('still processing unchanged', apply_gateway_result($p, 'PENDING', '')['outcome'] === 'unchanged');
check('Daraja codes mapped', DarajaGateway::stateFor(0) === 'SUCCESS' && DarajaGateway::stateFor(1032) === 'CANCELLED'
    && DarajaGateway::stateFor(1037) === 'TIMEOUT' && DarajaGateway::stateFor(2001) === 'FAILED');

echo "Payments (simulator)\n";
check('success', status(pay('SUCCESS')) === 'SUCCESS');
check('cancelled', status(pay('USER_CANCELLED')) === 'CANCELLED');
check('insufficient funds', status(pay('INSUFFICIENT_FUNDS')) === 'FAILED');
$before = count_of('SELECT COUNT(*) FROM transactions');
$lost = pay('LOST_CALLBACK');
check('record exists though callback lost', count_of('SELECT COUNT(*) FROM transactions') === $before + 1 && status($lost) === 'PENDING');
check('timeout keeps the record', status(pay('TIMEOUT')) === 'TIMEOUT');
foreach ([['', '254712345678', 'x', '10'], ['A', '0712', 'x', '10'], ['A', '254712345678', 'x', '-5']] as $bad) {
    try { start_payment(...$bad); check('bad input rejected', false); } catch (InvalidArgumentException) { check('bad input rejected', true); }
}

echo "Reconciliation (simulator)\n";
$dup = pay('DUPLICATE_CHARGE');
$r = reconcile();
check('lost callback recovered', status($lost) === 'SUCCESS' && $r['auto_fixed'] >= 1);
check('duplicate charge flagged with incident', status($dup) === 'FLAGGED' && count_of("SELECT COUNT(*) FROM incidents WHERE incident_type='DUPLICATE_CHARGE'") === 1);
reconcile();
check('second run adds no duplicate incident', count_of("SELECT COUNT(*) FROM incidents WHERE incident_type='DUPLICATE_CHARGE'") === 1);
$ok = pay('SUCCESS');
db()->exec('DELETE FROM gateway_ledger WHERE reference = ' . db()->quote(find_transaction($ok)['reference']));
reconcile();
check('local SUCCESS with no charge flagged', status($ok) === 'FLAGGED');
$m = dashboard_metrics();
check('dashboard counts add up', $m['total'] === array_sum($m['by_status']));

echo "Login\n";
create_user('tester', 'secret123');
check('right password', login('tester', 'secret123'));
check('wrong password', !login('tester', 'nope'));

echo "Daraja client against a pretend Safaricom\n";
$port = random_int(20000, 29999);
$fake = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", ROOT . '/tests/fake_safaricom.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
$up = false;
for ($i = 0; $i < 30 && !$up; $i++) { usleep(100000); $up = @fsockopen('127.0.0.1', $port) !== false; }
check('pretend Safaricom started', $up);
if ($up) {
    $daraja = new DarajaGateway(['base_url' => "http://127.0.0.1:$port", 'key' => 'k', 'secret' => 's', 'shortcode' => '174379',
        'passkey' => 'pk', 'type' => 'CustomerPayBillOnline', 'party_b' => '174379', 'callback' => 'https://example.test/index.php/callback/testtoken', 'ca_bundle' => null]);
    $t = ['reference' => 'PGTEST0001', 'amount' => '100.00'];
    $push = $daraja->push($t, '254712345678');
    check('STK push gives PENDING and a request id', $push['state'] === 'PENDING' && $push['request_id'] === 'ws_CO_TEST_1');
    $q = $daraja->query(['gateway_request_id' => 'ws_CO_TEST_1']);
    check('query of paid request gives SUCCESS', $q['state'] === 'SUCCESS');
    check('query of cancelled request gives CANCELLED', $daraja->query(['gateway_request_id' => 'cancelled'])['state'] === 'CANCELLED');
    check('query still processing gives PENDING', $daraja->query(['gateway_request_id' => 'busy'])['state'] === 'PENDING');
    $bad = new DarajaGateway(['base_url' => "http://127.0.0.1:$port", 'key' => 'wrong', 'secret' => 'x', 'shortcode' => '1', 'passkey' => 'p', 'type' => 'x', 'party_b' => '1', 'callback' => 'x', 'ca_bundle' => null]);
    try { $bad->push($t, '254712345678'); check('bad keys give a clear error', false); } catch (RuntimeException $e) { check('bad keys give a clear error', str_contains($e->getMessage(), 'access token')); }
    proc_terminate($fake);
}

echo "Callback handling\n";
$cbTxn = pay('LOST_CALLBACK');
db()->prepare('UPDATE transactions SET gateway_request_id=? WHERE id=?')->execute(['ws_CO_CB', $cbTxn]);
$body = ['Body' => ['stkCallback' => ['CheckoutRequestID' => 'ws_CO_CB', 'ResultCode' => 0, 'ResultDesc' => 'ok',
    'CallbackMetadata' => ['Item' => [['Name' => 'Amount', 'Value' => 1000], ['Name' => 'MpesaReceiptNumber', 'Value' => 'QWE123']]]]]];
$parsed = DarajaGateway::parseCallback($body);
check('callback parsed', $parsed['receipt'] === 'QWE123' && $parsed['state'] === 'SUCCESS');
handle_callback($parsed);
check('callback marks SUCCESS with receipt', status($cbTxn) === 'SUCCESS' && find_transaction($cbTxn)['mpesa_receipt'] === 'QWE123');
check('repeated callback ignored', handle_callback(['state' => 'FAILED'] + $parsed) === 'ignored' && status($cbTxn) === 'SUCCESS');
check('unknown request id handled', handle_callback(['request_id' => 'nope'] + $parsed) === 'unknown');

@unlink($dbFile);
echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) FAILED.\n";
exit($failed === 0 ? 0 : 1);
