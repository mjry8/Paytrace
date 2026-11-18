<?php
// Customer sees what happened and can report a problem. Found by reference, not by number.
$title = 'Receipt';
$st = db()->prepare('SELECT id FROM transactions WHERE reference = ?');
$st->execute([query_param('ref')]);
$found = $st->fetch();
$txn = $found ? find_transaction((int) $found['id']) : null;
if (!$txn) {
    http_response_code(404);
    exit('Receipt not found.');
}
if (is_post()) {
    csrf_check();
    $type = array_key_exists(post('incident_type'), INCIDENT_TYPES) ? post('incident_type') : 'OTHER';
    $trust = max(1, min(5, (int) post('trust_score') ?: 3));
    if (post('description') === '') {
        flash('error', 'Please describe the problem.');
    } else {
        open_incident((int) $txn['id'], $type, post('description'), 'MANUAL', $trust);
        flash('ok', 'Thank you. Your report was recorded and staff will review it.');
        redirect(url('receipt', ['ref' => $txn['reference']]));
    }
}
$meta = in_array($txn['status'], ['PENDING', 'TIMEOUT'], true) && !use_simulator() ? '<meta http-equiv="refresh" content="8">' : '';
require __DIR__ . '/_header.php';
echo $meta;
?>
<h1>Receipt <?= e($txn['reference']) ?></h1>
<div class="card narrow">
  <p>Status: <span class="badge <?= e($txn['status']) ?>"><?= e($txn['status']) ?></span></p>
  <p><?= e($txn['customer_name']) ?> &middot; <?= e($txn['item']) ?> &middot; KES <?= money($txn['amount']) ?></p>
  <p>M-Pesa receipt: <?= e($txn['mpesa_receipt'] ?: 'not yet issued') ?></p>
  <p class="mute"><?= e($txn['gateway_message']) ?></p>
  <?php if ($txn['status'] === 'PENDING'): ?>
    <p>Check your phone and enter your M-Pesa PIN. This page refreshes by itself.</p>
  <?php endif; ?>
</div>
<h2>Something wrong with this payment?</h2>
<form method="post" class="card narrow">
  <?= csrf_field() ?>
  <label>Problem
    <select name="incident_type">
      <?php foreach (INCIDENT_TYPES as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Details <textarea name="description" rows="3" required></textarea></label>
  <label>How much do you trust this shop's payments now? (1 = not at all, 5 = completely)
    <select name="trust_score"><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= $i === 3 ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select>
  </label>
  <button>Send report</button>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
