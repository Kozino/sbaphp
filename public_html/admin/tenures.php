<?php
require __DIR__ . '/_auth.php';
const SELF = '/admin/tenures.php';

function tenure_years(): array
{
    $s = valid_year($_POST['start_year'] ?? null);
    $e = valid_year($_POST['end_year'] ?? null);
    if (!$s || !$e || $e < $s) {
        throw new RuntimeException('Enter valid years — the end year must not be before the start year.');
    }
    return [$s, $e];
}

admin_action(SELF, function () {
    $act = $_POST['action'] ?? '';
    if ($act === 'add') {
        [$s, $e] = tenure_years();
        q('INSERT INTO executive_tenures (start_year, end_year) VALUES (?,?)', [$s, $e]);
        return "Tenure $s – $e added. Now add its executives below.";
    }
    if ($act === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if (!val('SELECT 1 FROM executive_tenures WHERE id = ?', [$id])) throw new RuntimeException('Tenure not found.');
        [$s, $e] = tenure_years();
        q('UPDATE executive_tenures SET start_year=?, end_year=? WHERE id=?', [$s, $e, $id]);
        return "Tenure updated to $s – $e.";
    }
    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $imgs = all('SELECT image FROM executives WHERE tenure_id = ?', [$id]);
        q('DELETE FROM executive_tenures WHERE id = ?', [$id]); // cascades to its executives
        foreach ($imgs as $i) delete_upload($i['image'], upload_dir());
        return 'Tenure and its executives removed.';
    }
    throw new RuntimeException('Unknown action.');
});

$tenures = all('SELECT * FROM executive_tenures ORDER BY end_year DESC, id DESC');
$execs = all('SELECT * FROM executives WHERE tenure_id IS NOT NULL ORDER BY sort_order, id');

admin_start('Past Executives', 'tenures', $ADMIN);
echo admin_head_flash();
?>
<div class="panel">
  <h2>Step 1 — Add a tenure</h2>
  <p class="muted" style="margin-top:0">Enter the start and end year of the outgoing team first. Then, on each officer, go to Executives and set their tenure (or use “Add executive” below). Changes appear on the public Executives page under <em>Past Executives</em>.</p>
  <form class="form" method="post" action="<?= SELF ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="form-row">
      <label>Start year<input type="number" name="start_year" min="1900" max="2200" required placeholder="e.g. 2012"></label>
      <label>End year<input type="number" name="end_year" min="1900" max="2200" required placeholder="e.g. 2014"></label>
    </div>
    <div><button class="btn" type="submit">Add tenure</button></div>
  </form>
</div>

<h2>Step 2 — Tenures and their executives</h2>
<?php if (!$tenures): ?><p class="muted">No past tenures yet.</p><?php endif; ?>
<?php foreach ($tenures as $t): $list = array_values(array_filter($execs, fn($x) => (int)$x['tenure_id'] === (int)$t['id'])); ?>
  <div class="panel">
    <div class="toolbar">
      <h2 style="margin:0"><?= (int)$t['start_year'] ?> – <?= (int)$t['end_year'] ?> <span class="muted" style="font-size:.9rem">· <?= count($list) ?> officer(s)</span></h2>
      <div class="row-actions">
        <a class="btn btn-sm btn-gold" href="/admin/executives.php?tenure=<?= (int)$t['id'] ?>">Add executive to this tenure</a>
        <form method="post" action="<?= SELF ?>" onsubmit="return confirm('Delete this tenure AND all <?= count($list) ?> executive(s) in it? This cannot be undone.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete tenure</button></form>
      </div>
    </div>
    <form class="form-row" method="post" action="<?= SELF ?>" style="align-items:end;margin-bottom:12px">
      <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <label>Start year<input type="number" name="start_year" min="1900" max="2200" required value="<?= (int)$t['start_year'] ?>"></label>
      <label>End year<input type="number" name="end_year" min="1900" max="2200" required value="<?= (int)$t['end_year'] ?>"></label>
      <div><button class="btn btn-sm btn-ghost" type="submit">Update years</button></div>
    </form>
    <?php if ($list): ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Position</th><th class="num">Order</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $e): ?>
          <tr><td><?= h($e['name']) ?></td><td><?= h($e['position']) ?></td><td class="num"><?= (int)$e['sort_order'] ?></td>
              <td><a class="btn btn-sm btn-ghost" href="/admin/executives.php?edit=<?= (int)$e['id'] ?>">Edit</a></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php else: ?><p class="muted">No executives in this tenure yet.</p><?php endif; ?>
  </div>
<?php endforeach;
admin_end();
