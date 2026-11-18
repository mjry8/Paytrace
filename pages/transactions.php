<?php
$title = 'Transactions';
$status = query_param('status');
$sql = 'SELECT t.*, o.customer_name, o.item FROM transactions t JOIN orders o ON o.id = t.order_id';
$args = [];
if (in_array($status, STATUSES, true)) {
    $sql .= ' WHERE t.status = ?';
    $args[] = $status;
}
$st = db()->prepare($sql . ' ORDER BY t.id DESC LIMIT 200');
$st->execute($args);
$rows = $st->fetchAll();
require __DIR__ . '/_header.php';
?>
<h1>Transactions</h1>
<p>Filter:
  <a href="<?= url('transactions') ?>">All</a>
  <?php foreach (STATUSES as $s): ?> &middot; <a href="<?= url('transactions', ['status' => $s]) ?>"><?= e($s) ?></a><?php endforeach; ?>
</p>
<div class="card scroll">
<table>
  <tr><th>Reference</th><th>Customer</th><th>Item</th><th>KES</th><th>Status</th><th>Gateway</th><th>Receipt</th><th>Message</th><th>Time</th></tr>
  <?php foreach ($rows as $t): ?>
  <tr>
    <td><a href="<?= url('receipt', ['ref' => $t['reference']]) ?>"><?= e($t['reference']) ?></a></td>
    <td><?= e($t['customer_name']) ?></td><td><?= e($t['item']) ?></td><td><?= money($t['amount']) ?></td>
    <td><span class="badge <?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
    <td><?= e($t['gateway']) ?></td><td><?= e($t['mpesa_receipt']) ?></td>
    <td class="mute"><?= e($t['gateway_message']) ?></td><td><?= e($t['created_at']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9" class="mute">No transactions.</td></tr><?php endif; ?>
</table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
