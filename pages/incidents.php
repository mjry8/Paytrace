<?php
$title = 'Incidents';
if (is_post()) {
    csrf_check();
    if (post('action') === 'resolve') {
        resolve_incident((int) post('incident_id'), post('note'));
        flash('ok', 'Incident resolved.');
    } elseif (post('action') === 'new') {          // staff log a problem by hand
        $st = db()->prepare('SELECT id FROM transactions WHERE reference = ?');
        $st->execute([post('reference')]);
        $t = $st->fetch();
        if (!$t || post('description') === '') {
            flash('error', 'Enter a valid transaction reference and a description.');
        } else {
            $type = array_key_exists(post('incident_type'), INCIDENT_TYPES) ? post('incident_type') : 'OTHER';
            open_incident((int) $t['id'], $type, post('description'), 'MANUAL');
            flash('ok', 'Incident logged.');
        }
    }
    redirect(url('incidents'));
}
$rows = db()->query('SELECT i.*, t.reference FROM incidents i JOIN transactions t ON t.id = i.transaction_id ORDER BY (i.status = \'OPEN\') DESC, i.id DESC')->fetchAll();
require __DIR__ . '/_header.php';
?>
<h1>Incidents</h1>
<div class="card scroll">
<table>
  <tr><th>#</th><th>Transaction</th><th>Type</th><th>Description</th><th>Trust</th><th>Source</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $i): ?>
  <tr>
    <td><?= (int) $i['id'] ?></td><td><?= e($i['reference']) ?></td><td><?= e(INCIDENT_TYPES[$i['incident_type']] ?? $i['incident_type']) ?></td>
    <td><?= e($i['description']) ?><?php if ($i['resolution_note']): ?><br><span class="mute">Resolution: <?= e($i['resolution_note']) ?></span><?php endif; ?></td>
    <td><?= e((string) ($i['trust_score'] ?? '-')) ?></td><td><?= e($i['source']) ?></td>
    <td><span class="badge <?= e($i['status']) ?>"><?= e($i['status']) ?></span></td>
    <td><?php if ($i['status'] === 'OPEN'): ?>
      <form method="post" class="inline"><?= csrf_field() ?>
        <input type="hidden" name="action" value="resolve"><input type="hidden" name="incident_id" value="<?= (int) $i['id'] ?>">
        <input name="note" placeholder="Resolution note"><button>Resolve</button></form>
    <?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="mute">No incidents.</td></tr><?php endif; ?>
</table>
</div>
<h2>Log a problem by hand</h2>
<form method="post" class="card narrow">
  <?= csrf_field() ?><input type="hidden" name="action" value="new">
  <label>Transaction reference <input name="reference" placeholder="PG1A2B3C4D" required></label>
  <label>Type <select name="incident_type"><?php foreach (INCIDENT_TYPES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label>Description <textarea name="description" rows="2" required></textarea></label>
  <button>Log incident</button>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
