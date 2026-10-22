<?php
require __DIR__ . '/_auth.php';
const SELF = '/admin/gallery.php';

admin_action(SELF, function () {
    $act = $_POST['action'] ?? '';
    if ($act === 'delete') {
        $item = one('SELECT * FROM gallery WHERE id = ?', [(int)$_POST['id']]);
        if (!$item) throw new RuntimeException('Gallery item not found.');
        q('DELETE FROM gallery WHERE id = ?', [$item['id']]);
        delete_upload($item['image'], upload_dir());
        return 'Photo deleted.';
    }
    $year = valid_year($_POST['event_year'] ?? null, 1900, 2100) ?? (int)date('Y');
    $title = trim((string)($_POST['title'] ?? ''));
    $caption = trim((string)($_POST['caption'] ?? ''));
    $img = save_upload('image', 'gallery', upload_dir(), IMAGE_TYPES);
    if ($act === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $old = one('SELECT * FROM gallery WHERE id = ?', [$id]);
        if (!$old) throw new RuntimeException('Gallery item not found.');
        q('UPDATE gallery SET title=?, caption=?, image=?, event_year=? WHERE id=?', [$title, $caption, $img ?? $old['image'], $year, $id]);
        if ($img) delete_upload($old['image'], upload_dir());
        return 'Photo updated.';
    }
    if (!$img) throw new RuntimeException('Please choose an image to upload.');
    q('INSERT INTO gallery (title, caption, image, event_year) VALUES (?,?,?,?)', [$title, $caption, $img, $year]);
    return 'Photo added to the gallery.';
});

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? one('SELECT * FROM gallery WHERE id = ?', [$editId]) : null;
$rows = all('SELECT * FROM gallery ORDER BY event_year DESC, id DESC');

admin_start('Gallery', 'gallery', $ADMIN);
echo admin_head_flash();
?>
<div class="panel">
  <h2><?= $edit ? 'Edit photo' : 'Upload a photo' ?></h2>
  <form class="form" method="post" action="<?= SELF ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <label>Title<input name="title" maxlength="255" value="<?= h($edit['title'] ?? '') ?>"></label>
      <label>Year of the event<input type="number" name="event_year" min="1900" max="2100" required value="<?= h($edit['event_year'] ?? date('Y')) ?>"></label>
    </div>
    <label>Caption<input name="caption" maxlength="500" value="<?= h($edit['caption'] ?? '') ?>"></label>
    <label>Photo <small>(JPG, PNG, GIF or WebP, max 20 MB)<?= $edit ? ' — leave empty to keep the current photo' : '' ?></small><input type="file" name="image" accept="image/*" <?= $edit ? '' : 'required' ?>></label>
    <div class="row-actions"><button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Upload' ?></button>
      <?php if ($edit): ?><a class="btn btn-ghost" href="<?= SELF ?>">Cancel</a><?php endif; ?></div>
  </form>
</div>
<div class="gallery-grid">
  <?php if (!$rows) echo '<p class="muted">No photos yet.</p>';
  foreach ($rows as $g): ?>
    <div class="card" style="padding:10px">
      <img src="<?= h(upload_url($g['image'])) ?>" alt="" style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:10px">
      <strong><?= h($g['title'] ?: 'Untitled') ?></strong>
      <p class="muted" style="margin:4px 0"><?= (int)$g['event_year'] ?> · <?= h($g['caption']) ?></p>
      <div class="row-actions">
        <a class="btn btn-sm btn-ghost" href="<?= SELF ?>?edit=<?= (int)$g['id'] ?>">Edit</a>
        <form method="post" action="<?= SELF ?>" onsubmit="return confirm('Delete this photo?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php admin_end();
