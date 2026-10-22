<?php
require __DIR__ . '/_auth.php';
const SELF = '/admin/news.php';

admin_action(SELF, function () {
    $act = $_POST['action'] ?? '';
    if ($act === 'delete') {
        $item = one('SELECT * FROM news WHERE id = ?', [(int)$_POST['id']]);
        if (!$item) throw new RuntimeException('News item not found.');
        q('DELETE FROM news WHERE id = ?', [$item['id']]);
        delete_upload($item['image'], upload_dir());
        return 'News item deleted.';
    }
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') throw new RuntimeException('Title is required.');
    $date = (string)($_POST['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) $date = date('Y-m-d');
    $body = (string)($_POST['body'] ?? '');
    $img = save_upload('image', 'news', upload_dir(), IMAGE_TYPES);
    if ($act === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $old = one('SELECT * FROM news WHERE id = ?', [$id]);
        if (!$old) throw new RuntimeException('News item not found.');
        q('UPDATE news SET title=?, body=?, date=?, image=? WHERE id=?', [$title, $body, $date, $img ?? $old['image'], $id]);
        if ($img) delete_upload($old['image'], upload_dir());
        return 'News item updated.';
    }
    q('INSERT INTO news (title, body, date, image) VALUES (?,?,?,?)', [$title, $body, $date, $img ?? '']);
    return 'News item published.';
});

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? one('SELECT * FROM news WHERE id = ?', [$editId]) : null;
$rows = all('SELECT * FROM news ORDER BY date DESC, id DESC');

admin_start('News', 'news', $ADMIN);
echo admin_head_flash();
?>
<div class="panel">
  <h2><?= $edit ? 'Edit news item' : 'Post news' ?></h2>
  <form class="form" method="post" action="<?= SELF ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <label>Title *<input name="title" required maxlength="255" value="<?= h($edit['title'] ?? '') ?>"></label>
      <label>Date<input type="date" name="date" value="<?= h($edit['date'] ?? date('Y-m-d')) ?>"></label>
    </div>
    <label>Story <small>(plain text; blank lines start new paragraphs)</small><textarea name="body" rows="8"><?= h($edit['body'] ?? '') ?></textarea></label>
    <label>Cover image <small>(JPG, PNG, GIF or WebP, max 20 MB)<?= $edit && $edit['image'] ? ' — leave empty to keep the current image' : '' ?></small><input type="file" name="image" accept="image/*"></label>
    <?php if ($edit && $edit['image']): ?><img src="<?= h(upload_url($edit['image'])) ?>" alt="" style="max-width:260px;border-radius:10px"><?php endif; ?>
    <div class="row-actions"><button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Publish' ?></button>
      <?php if ($edit): ?><a class="btn btn-ghost" href="<?= SELF ?>">Cancel</a><?php endif; ?></div>
  </form>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Title</th><th>Image</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows) echo empty_row(4, 'No news yet.');
  foreach ($rows as $n): ?>
    <tr>
      <td><?= h(fmt_date_short($n['date'])) ?></td>
      <td><a href="/news-detail.php?id=<?= (int)$n['id'] ?>" target="_blank"><?= h($n['title']) ?></a></td>
      <td><?= $n['image'] ? badge('Yes', 'ok') : badge('No', 'muted') ?></td>
      <td><div class="row-actions">
        <a class="btn btn-sm btn-ghost" href="<?= SELF ?>?edit=<?= (int)$n['id'] ?>">Edit</a>
        <form method="post" action="<?= SELF ?>" onsubmit="return confirm('Delete this news item?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php admin_end();
