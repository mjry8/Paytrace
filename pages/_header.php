<?php /* Top of every page. Set $title before including. */ $user = current_user(); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'PayGuard') ?> - PayGuard</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav>
  <a class="brand" href="<?= url('home') ?>">PayGuard</a>
  <a href="<?= url('checkout') ?>">Checkout</a>
  <?php if ($user): ?>
    <a href="<?= url('dashboard') ?>">Dashboard</a>
    <a href="<?= url('transactions') ?>">Transactions</a>
    <a href="<?= url('incidents') ?>">Incidents</a>
    <a href="<?= url('reconciliation') ?>">Reconciliation</a>
    <a class="right" href="<?= url('logout') ?>">Log out (<?= e($user['username']) ?>)</a>
  <?php else: ?>
    <a class="right" href="<?= url('login') ?>">Staff login</a>
  <?php endif; ?>
</nav>
<main>
<?php if (use_simulator()): ?>
  <p class="banner">Simulator mode: no real money moves. Set USE_SIMULATOR=false in .env to use Safaricom.</p>
<?php endif; ?>
<?php foreach (take_flashes() as [$type, $msg]): ?>
  <p class="flash <?= e($type) ?>"><?= e($msg) ?></p>
<?php endforeach; ?>
