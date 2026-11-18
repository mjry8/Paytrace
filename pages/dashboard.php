<?php
$title = 'Dashboard';
$m = dashboard_metrics();
require __DIR__ . '/_header.php';
?>
<h1>Dashboard</h1>
<div class="tiles">
  <div class="tile"><b><?= $m['total'] ?></b>Transactions</div>
  <div class="tile"><b><?= $m['success_rate'] ?>%</b>Success rate</div>
  <div class="tile <?= $m['pending'] ? 'warn' : '' ?>"><b><?= $m['pending'] ?></b>Pending / timed out</div>
  <div class="tile <?= $m['open'] ? 'warn' : '' ?>"><b><?= $m['open'] ?></b>Open incidents</div>
  <div class="tile"><b><?= $m['avg_trust'] ?? '-' ?></b>Average trust (1-5)</div>
</div>
<div class="card">
  <h2>By status</h2>
  <?php foreach ($m['by_status'] as $status => $n): ?>
    <span class="badge <?= e($status) ?>"><?= e($status) ?> <?= $n ?></span>
  <?php endforeach; ?>
</div>
<p><a class="button" href="<?= url('reconciliation') ?>">Go to reconciliation</a></p>
<?php require __DIR__ . '/_footer.php'; ?>
