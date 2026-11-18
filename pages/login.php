<?php
$title = 'Staff login';
if (is_post()) {
    csrf_check();
    if (login(post('username'), (string) ($_POST['password'] ?? ''))) {
        redirect(url('dashboard'));
    }
    flash('error', 'Wrong username or password.');
}
require __DIR__ . '/_header.php';
?>
<h1>Staff login</h1>
<form method="post" class="card narrow">
  <?= csrf_field() ?>
  <label>Username <input name="username" required autofocus></label>
  <label>Password <input name="password" type="password" required></label>
  <button>Log in</button>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
