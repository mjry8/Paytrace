<?php
$title = 'Welcome';
require __DIR__ . '/_header.php';
?>

<h1>PayGuard Payment Reliability System</h1>

<div class="card">
  <p>Every payment is recorded <b>before</b> the gateway is called. If the gateway fails, times out, or loses its callback, the record is still there and staff can reconcile it.</p>
  <p class="mute">This system was built to solve three problems: unexplained charges, disappearing payment records, and reconciliation overload.</p>
</div>

<div class="tiles">
  <div class="tile"><b>1</b>Pay securely</div>
  <div class="tile"><b>2</b>Get a receipt</div>
  <div class="tile"><b>3</b>Staff reconciles if needed</div>
</div>

<p>
  <a class="button" href="<?= url('checkout') ?>">Start a Payment</a>
  <a class="button" href="<?= url('login') ?>" style="background:#6b7685">Staff Login</a>
</p>

<?php require __DIR__ . '/_footer.php'; ?>