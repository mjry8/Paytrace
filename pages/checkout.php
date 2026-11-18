<?php
// Customer pays. Saves the record FIRST (start_payment), then calls the gateway.
$title = 'Checkout';
$form = ['customer_name' => '', 'phone' => '', 'item' => '', 'amount' => ''];
if (is_post()) {
    csrf_check();
    foreach ($form as $k => $_) {
        $form[$k] = post($k);
    }
    try {
        $scenario = array_key_exists(post('scenario'), SCENARIOS) ? post('scenario') : 'SUCCESS';
        $id = start_payment($form['customer_name'], $form['phone'], $form['item'], $form['amount'], use_simulator() ? $scenario : '');
        redirect(url('receipt', ['ref' => find_transaction($id)['reference']]));
    } catch (InvalidArgumentException $err) {
        http_response_code(400);
        flash('error', $err->getMessage());
    }
}
require __DIR__ . '/_header.php';
?>
<h1>Checkout</h1>
<form method="post" class="card narrow">
  <?= csrf_field() ?>
  <label>Your name <input name="customer_name" value="<?= e($form['customer_name']) ?>" required></label>
  <label>M-Pesa phone number <input name="phone" value="<?= e($form['phone']) ?>" placeholder="254712345678" pattern="254[17][0-9]{8}" required></label>
  <label>Item <input name="item" value="<?= e($form['item']) ?>" required></label>
  <label>Amount (KES) <input name="amount" type="number" min="1" max="150000" step="1" value="<?= e($form['amount']) ?>" required></label>
  <?php if (use_simulator()): ?>
  <label>What should the simulator do?
    <select name="scenario">
      <?php foreach (SCENARIOS as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <button>Pay with M-Pesa</button>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
