<?php
$title = 'Reconciliation';
$report = null;
if (is_post()) {
    csrf_check();
    $report = reconcile();
}
$pending = db()->query("SELECT t.*, o.customer_name FROM transactions t JOIN orders o ON o.id = t.order_id WHERE t.status IN ('PENDING','TIMEOUT','FLAGGED') ORDER BY t.id DESC")->fetchAll();
require __DIR__ . '/_header.php';
?>
<h1>Reconciliation</h1>
<form method="post" class="card">
  <?= csrf_field() ?>
  <p><?= use_simulator()
      ? 'Compares every transaction with the simulator\'s own ledger, repairs payments that really succeeded, and flags anything that does not match.'
      : 'Asks Safaricom about every PENDING or TIMEOUT payment, updates it, and opens an incident when it cannot be checked.' ?></p>
  <button>Run reconciliation now</button>
</form>
<?php if ($report): ?>
<div class="card">
  <h2>Result</h2>
  <p>Checked <b><?= $report['checked'] ?></b> &middot; matched <b><?= $report['matched'] ?></b> &middot;
     fixed <b><?= $report['auto_fixed'] ?></b> &middot; flagged or still open <b><?= $report['flagged'] ?></b></p>
  <table>
    <?php foreach ($report['items'] as [$ref, $note]): ?><tr><td><?= e($ref) ?></td><td><?= e($note) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$report['items']): ?><tr><td class="mute">Nothing to fix.</td></tr><?php endif; ?>
  </table>
</div>
<?php endif; ?>
<div class="card scroll">
  <h2>Needs attention (<?= count($pending) ?>)</h2>
  <table>
    <tr><th>Reference</th><th>Customer</th><th>KES</th><th>Status</th><th>Gateway message</th></tr>
    <?php foreach ($pending as $t): ?>
    <tr><td><?= e($t['reference']) ?></td><td><?= e($t['customer_name']) ?></td><td><?= money($t['amount']) ?></td>
        <td><span class="badge <?= e($t['status']) ?>"><?= e($t['status']) ?></span></td><td class="mute"><?= e($t['gateway_message']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$pending): ?><tr><td class="mute">All clear.</td></tr><?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
