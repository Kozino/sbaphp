<?php
require __DIR__ . '/_auth.php';
const SELF = '/admin/executives.php';

/** Tenure id from a form field: '' / missing = current executives (NULL). */
function tenure_from_post($raw): ?int
{
    $raw = trim((string)($raw ?? ''));
    if ($raw === '') return null;
    $id = filter_var($raw, FILTER_VALIDATE_INT);
    if ($id === false || !val('SELECT 1 FROM executive_tenures WHERE id = ?', [$id])) {
        throw new RuntimeException('That tenure no longer exists. Choose again.');
    }
    return (int)$id;
}

admin_action(SELF, function () {
    $act = $_POST['action'] ?? '';
    if ($act === 'delete') {
        $e = one('SELECT * FROM executives WHERE id = ?', [(int)$_POST['id']]);
        if (!$e) throw new RuntimeException('Executive not found.');
        q('DELETE FROM executives WHERE id = ?', [$e['id']]);
        delete_upload($e['image'], upload_dir());
        return 'Executive removed.';
    }
    $name = trim((string)($_POST['name'] ?? ''));
    $position = trim((string)($_POST['position'] ?? ''));
    if ($name === '') throw new RuntimeException('Name is required.');
    if ($position === '') throw new RuntimeException('Position is required.');
    $sort = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
    $sort = $sort === false ? 0 : (int)$sort;
    $tenure = tenure_from_post($_POST['tenure_id'] ?? '');
    $img = save_upload('image', 'exec', upload_dir(), IMAGE_TYPES);
    if ($act === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $old = one('SELECT * FROM executives WHERE id = ?', [$id]);
        if (!$old) throw new RuntimeException('Executive not found.');
        q('UPDATE executives SET name=?, position=?, image=?, sort_order=?, tenure_id=? WHERE id=?',
            [$name, $position, $img ?? $old['image'], $sort, $tenure, $id]);
        if ($img) delete_upload($old['image'], upload_dir());
        return 'Executive updated.';
    }
    q('INSERT INTO executives (name, position, image, sort_order, tenure_id) VALUES (?,?,?,?,?)',
        [$name, $position, $img ?? '', $sort, $tenure]);
    return 'Executive added' . ($tenure ? ' to the selected tenure.' : ' to the current executives.');
});

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? one('SELECT * FROM executives WHERE id = ?', [$editId]) : null;
$presetTenure = $edit ? $edit['tenure_id'] : (valid_year($_GET['tenure'] ?? null, 1, 999999) ?? null);
$tenures = all('SELECT * FROM executive_tenures ORDER BY end_year DESC, id DESC');
$rows = all('SELECT e.*, t.start_year, t.end_year FROM executives e LEFT JOIN executive_tenures t ON t.id = e.tenure_id ORDER BY (e.tenure_id IS NULL) DESC, t.end_year DESC, e.sort_order, e.id');

admin_start('Executives', 'executives', $ADMIN);
echo admin_head_flash();
?>
<div class="panel">
  <h2><?= $edit ? 'Edit executive' : 'Add an executive' ?></h2>
  <p class="muted" style="margin-top:0">Current executives stay in “Current”. When a new team takes office, add its tenure first (Past Executives), then move the outgoing officers to that tenure by editing them here.</p>
  <form class="form" method="post" action="<?= SELF ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <label>Full name *<input name="name" required maxlength="191" value="<?= h($edit['name'] ?? '') ?>"></label>
      <label>Position *<input name="position" required maxlength="191" value="<?= h($edit['position'] ?? '') ?>" placeholder="e.g. President"></label>
      <label>Tenure
        <select name="tenure_id">
          <option value="">Current (in office)</option>
          <?php foreach ($tenures as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= (int)$presetTenure === (int)$t['id'] ? 'selected' : '' ?>><?= (int)$t['start_year'] ?> – <?= (int)$t['end_year'] ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="form-row">
      <label>Display order <small>(lower shows first)</small><input type="number" name="sort_order" value="<?= h($edit['sort_order'] ?? 0) ?>"></label>
      <label>Photo <small>(JPG/PNG/WebP, max 20 MB)<?= $edit && $edit['image'] ? ' — blank keeps current' : '' ?></small><input type="file" name="image" accept="image/*"></label>
    </div>
    <?php if ($edit && $edit['image']): ?><img src="<?= h(upload_url($edit['image'])) ?>" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:50%"><?php endif; ?>
    <div class="row-actions"><button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Add executive' ?></button>
      <?php if ($edit): ?><a class="btn btn-ghost" href="<?= SELF ?>">Cancel</a><?php endif; ?></div>
  </form>
</div>

<div class="table-wrap"><table>
  <thead><tr><th>Photo</th><th>Name</th><th>Position</th><th>Tenure</th><th class="num">Order</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows) echo empty_row(6, 'No executives yet.');
  foreach ($rows as $e): ?>
    <tr>
      <td><?= $e['image'] ? '<img src="' . h(upload_url($e['image'])) . '" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover">' : '<span class="muted">—</span>' ?></td>
      <td><?= h($e['name']) ?></td>
      <td><?= h($e['position']) ?></td>
      <td><?= $e['tenure_id'] ? badge((int)$e['start_year'] . ' – ' . (int)$e['end_year'], 'navy') : badge('Current', 'ok') ?></td>
      <td class="num"><?= (int)$e['sort_order'] ?></td>
      <td><div class="row-actions">
        <a class="btn btn-sm btn-ghost" href="<?= SELF ?>?edit=<?= (int)$e['id'] ?>">Edit</a>
        <form method="post" action="<?= SELF ?>" onsubmit="return confirm('Remove this executive?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Remove</button></form>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php admin_end();
