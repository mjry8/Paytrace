<?php
declare(strict_types=1);

// The payment story in four steps: record -> call gateway -> save the answer -> reconcile later.

function find_transaction(int $id): ?array
{
    $st = db()->prepare('SELECT t.*, o.customer_name, o.phone, o.item FROM transactions t JOIN orders o ON o.id = t.order_id WHERE t.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Save a gateway answer onto a transaction using the rules in Rules.php. Returns the outcome word. */
function save_result(array $txn, array $answer): string
{
    $r = apply_gateway_result($txn, $answer['state'], $answer['message'], $answer['receipt'] ?? null, $answer['amount'] ?? null);
    if ($r['outcome'] === 'updated' || $r['outcome'] === 'flagged') {
        db()->prepare('UPDATE transactions SET status=?, gateway_message=?, mpesa_receipt=?, updated_at=? WHERE id=?')
            ->execute([$r['status'], $r['message'], $r['receipt'], now(), $txn['id']]);
    }
    if ($r['outcome'] === 'flagged') {
        open_incident((int) $txn['id'], 'OTHER', $r['incident']);
    }
    return $r['outcome'];
}

/**
 * Take a payment. THE KEY RULE: the order and a PENDING transaction are committed
 * to the database BEFORE the gateway is called, so a record always exists.
 * Returns the transaction id. Throws InvalidArgumentException for bad form input.
 */
function start_payment(string $name, string $phone, string $item, string $amount, string $scenario = 'SUCCESS'): int
{
    if ($name === '' || $item === '') {
        throw new InvalidArgumentException('Name and item are required.');
    }
    if (!preg_match('/^254[17]\d{8}$/', $phone)) {
        throw new InvalidArgumentException('Phone must look like 254712345678.');
    }
    if (!is_numeric($amount) || (float) $amount < 1 || (float) $amount > 150000) {
        throw new InvalidArgumentException('Amount must be between 1 and 150,000.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO orders (customer_name, phone, item, amount, created_at) VALUES (?,?,?,?,?)')
        ->execute([$name, $phone, $item, $amount, now()]);
    $orderId = (int) $pdo->lastInsertId();
    $reference = 'PG' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare("INSERT INTO transactions (order_id, reference, amount, status, gateway, reconciled, created_at, updated_at) VALUES (?,?,?,'PENDING',?,0,?,?)")
        ->execute([$orderId, $reference, $amount, gateway_name(), now(), now()]);
    $txnId = (int) $pdo->lastInsertId();
    $pdo->commit();                                   // <-- the permanent record exists from here on

    $txn = find_transaction($txnId);
    try {
        $answer = get_gateway()->push($txn, $phone, $scenario);
    } catch (Throwable $err) {                        // gateway down or unreachable: keep the record, mark TIMEOUT
        $answer = ['state' => 'TIMEOUT', 'message' => 'Gateway error: ' . $err->getMessage(), 'request_id' => null];
    }
    if (!empty($answer['request_id'])) {
        $pdo->prepare('UPDATE transactions SET gateway_request_id=? WHERE id=?')->execute([$answer['request_id'], $txnId]);
        $txn['gateway_request_id'] = $answer['request_id'];
    }
    save_result($txn, $answer);
    return $txnId;
}

/** Called when Safaricom posts a result to our callback address. */
function handle_callback(array $parsed): string
{
    $st = db()->prepare('SELECT * FROM transactions WHERE gateway_request_id = ?');
    $st->execute([$parsed['request_id']]);
    $txn = $st->fetch();
    if (!$txn) {
        return 'unknown';
    }
    return save_result($txn, $parsed);
}

// ---------------------------------------------------------------- incidents
/** Open an incident, unless the same one is already open for this transaction. */
function open_incident(int $txnId, string $type, string $description, string $source = 'AUTO', ?int $trust = null): void
{
    $st = db()->prepare("SELECT id FROM incidents WHERE transaction_id=? AND incident_type=? AND source=? AND status='OPEN'");
    $st->execute([$txnId, $type, $source]);
    if ($st->fetch()) {
        return;
    }
    db()->prepare('INSERT INTO incidents (transaction_id, incident_type, description, trust_score, status, source, created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$txnId, $type, $description, $trust, 'OPEN', $source, now()]);
}

function resolve_incident(int $id, string $note): void
{
    db()->prepare("UPDATE incidents SET status='RESOLVED', resolution_note=?, resolved_at=? WHERE id=?")->execute([$note, now(), $id]);
}

// ---------------------------------------------------------------- reconciliation
/**
 * Compare our records with the gateway's and repair or flag differences.
 * Returns ['checked','matched','auto_fixed','flagged','items'=>[[reference, note], ...]]
 */
function reconcile(): array
{
    $report = ['checked' => 0, 'matched' => 0, 'auto_fixed' => 0, 'flagged' => 0, 'items' => []];
    $rows = db()->query('SELECT * FROM transactions ORDER BY id')->fetchAll();
    $gw = get_gateway();
    foreach ($rows as $txn) {
        $report['checked']++;
        $before = $txn['status'];
        $note = use_simulator() ? reconcile_one_simulated($txn, $gw) : reconcile_one_live($txn, $gw);
        if ($note === null) {
            $report['matched']++;
            db()->prepare('UPDATE transactions SET reconciled=1 WHERE id=?')->execute([$txn['id']]);
            continue;
        }
        $after = find_transaction((int) $txn['id'])['status'];
        $fixed = $after !== $before && $after !== 'FLAGGED';
        $report[$fixed ? 'auto_fixed' : 'flagged']++;
        $report['items'][] = [$txn['reference'], $note];
    }
    return $report;
}

/** Simulator mode: the simulator's ledger is the "truth". Returns null when everything agrees. */
function reconcile_one_simulated(array $txn, SimulatorGateway $gw): ?string
{
    $charges = $gw->charges_for($txn['reference']);
    $n = count($charges);
    $id = (int) $txn['id'];
    if ($n > 1) {
        db()->prepare("UPDATE transactions SET status='FLAGGED', updated_at=? WHERE id=?")->execute([now(), $id]);
        open_incident($id, 'DUPLICATE_CHARGE', "Gateway charged this payment $n times.");
        return "Charged $n times";
    }
    if ($n === 1) {
        if (abs((float) $charges[0]['amount'] - (float) $txn['amount']) > 0.009) {
            db()->prepare("UPDATE transactions SET status='FLAGGED', updated_at=? WHERE id=?")->execute([now(), $id]);
            open_incident($id, 'OTHER', 'Amount mismatch between order and gateway.');
            return 'Amount mismatch';
        }
        if (in_array($txn['status'], ['PENDING', 'TIMEOUT'], true)) {
            save_result($txn, ['state' => 'SUCCESS', 'message' => 'Recovered by reconciliation', 'receipt' => $charges[0]['receipt'], 'amount' => (float) $charges[0]['amount']]);
            return 'Callback was lost: payment confirmed from gateway records';
        }
        if (in_array($txn['status'], ['FAILED', 'CANCELLED'], true)) {
            db()->prepare("UPDATE transactions SET status='FLAGGED', updated_at=? WHERE id=?")->execute([now(), $id]);
            open_incident($id, 'UNAUTHORIZED_CHARGE', 'We recorded this payment as ' . $txn['status'] . ' but the gateway charged the customer.');
            return 'Marked ' . $txn['status'] . ' but the customer was charged';
        }
        return null;
    }
    // Gateway has no charge.
    if ($txn['status'] === 'SUCCESS') {
        db()->prepare("UPDATE transactions SET status='FLAGGED', updated_at=? WHERE id=?")->execute([now(), $id]);
        open_incident($id, 'MISSING_RECORD', 'We recorded SUCCESS but the gateway has no charge for it.');
        return 'We say SUCCESS, gateway has no charge';
    }
    if (in_array($txn['status'], ['PENDING', 'TIMEOUT'], true)) {
        save_result($txn, ['state' => 'FAILED', 'message' => 'No charge found at the gateway', 'receipt' => null]);
        return 'No charge at gateway: closed as FAILED';
    }
    return null;
}

/** Live mode: ask Safaricom about every unfinished payment. Returns null when nothing needed doing. */
function reconcile_one_live(array $txn, DarajaGateway $gw): ?string
{
    if (!in_array($txn['status'], ['PENDING', 'TIMEOUT'], true)) {
        return null;
    }
    if (empty($txn['gateway_request_id'])) {
        open_incident((int) $txn['id'], 'MISSING_RECORD', 'No Safaricom request id, so this payment cannot be checked automatically.');
        return 'No request id: needs a staff check';
    }
    try {
        $answer = $gw->query($txn);
    } catch (Throwable $err) {
        return 'Could not ask Safaricom: ' . $err->getMessage();
    }
    if (save_result($txn, $answer) === 'unchanged') {
        return 'Safaricom says it is still processing';
    }
    return 'Updated from Safaricom: ' . $answer['state'];
}

// ---------------------------------------------------------------- dashboard
function dashboard_metrics(): array
{
    $pdo = db();
    $total = (int) $pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
    $success = (int) $pdo->query("SELECT COUNT(*) FROM transactions WHERE status='SUCCESS'")->fetchColumn();
    $byStatus = array_fill_keys(STATUSES, 0);
    foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM transactions GROUP BY status') as $row) {
        $byStatus[$row['status']] = (int) $row['n'];
    }
    $trust = $pdo->query('SELECT AVG(trust_score) FROM incidents WHERE trust_score IS NOT NULL')->fetchColumn();
    return [
        'total'        => $total,
        'success_rate' => $total ? round(100 * $success / $total, 1) : 0.0,
        'pending'      => $byStatus['PENDING'] + $byStatus['TIMEOUT'],
        'open'         => (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE status='OPEN'")->fetchColumn(),
        'avg_trust'    => $trust === null || $trust === false ? null : round((float) $trust, 1),
        'by_status'    => $byStatus,
    ];
}
