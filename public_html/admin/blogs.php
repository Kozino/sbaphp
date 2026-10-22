<?php
require __DIR__ . '/_auth.php';
const SELF = '/admin/blogs.php';

admin_action(SELF, function () {
    $act = $_POST['action'] ?? '';
    if ($act === 'delete') {
        $item = one('SELECT * FROM blogs WHERE id = ?', [(int)$_POST['id']]);
        if (!$item) throw new RuntimeException('Blog post not found.');
        q('DELETE FROM blogs WHERE id = ?', [$item['id']]);
        delete_upload($item['image'], upload_dir());
        return 'Blog post deleted.';
    }
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') throw new RuntimeException('Title is required.');
    $date = (string)($_POST['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) $date = date('Y-m-d');
    $author = trim((string)($_POST['author'] ?? ''));
    $excerpt = trim((string)($_POST['excerpt'] ?? ''));
    $body = (string)($_POST['body'] ?? '');
    $img = save_upload('image', 'blog', upload_dir(), IMAGE_TYPES);
    if ($act === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $old = one('SELECT * FROM blogs WHERE id = ?', [$id]);
        if (!$old) throw new RuntimeException('Blog post not found.');
        q('UPDATE blogs SET title=?, author=?, excerpt=?, body=?, date=?, image=? WHERE id=?',
            [$title, $author, $excerpt, $body, $date, $img ?? $old['image'], $id]);
        if ($img) delete_upload($old['image'], upload_dir());
        return 'Blog post updated.';
    }
    q('INSERT INTO blogs (title, author, excerpt, body, date, image) VALUES (?,?,?,?,?,?)', [$title, $author, $excerpt, $body, $date, $img ?? '']);
    return 'Blog post published.';
});

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? one('SELECT * FROM blogs WHERE id = ?', [$editId]) : null;
$rows = all('SELECT * FROM blogs ORDER BY date DESC, id DESC');

admin_start('Blogs', 'blogs', $ADMIN);
echo admin_head_flash();
?>
<div class="panel">
  <h2><?= $edit ? 'Edit blog post' : 'Write a blog post' ?></h2>
  <form class="form" method="post" action="<?= SELF ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <label>Title *<input name="title" required maxlength="255" value="<?= h($edit['title'] ?? '') ?>"></label>
      <label>Author<input name="author" maxlength="191" value="<?= h($edit['author'] ?? '') ?>"></label>
      <label>Date<input type="date" name="date" value="<?= h($edit['date'] ?? date('Y-m-d')) ?>"></label>
    </div>
    <label>Short summary<input name="excerpt" maxlength="500" value="<?= h($edit['excerpt'] ?? '') ?>"></label>
    <label>Post <small>(plain text; blank lines start new paragraphs)</small><textarea name="body" rows="10"><?= h($edit['body'] ?? '') ?></textarea></label>
    <label>Cover image <small>(optional, max 20 MB)</small><input type="file" name="image" accept="image/*"></label>
    <?php if ($edit && $edit['image']): ?><img src="<?= h(upload_url($edit['image'])) ?>" alt="" style="max-width:260px;border-radius:10px"><?php endif; ?>
    <div class="row-actions"><button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Publish' ?></button>
      <?php if ($edit): ?><a class="btn btn-ghost" href="<?= SELF ?>">Cancel</a><?php endif; ?></div>
  </form>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Title</th><th>Author</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows) echo empty_row(4, 'No blog posts yet.');
  foreach ($rows as $b): ?>
    <tr>
      <td><?= h(fmt_date_short($b['date'])) ?></td>
      <td><a href="/blog-detail.php?id=<?= (int)$b['id'] ?>" target="_blank"><?= h($b['title']) ?></a></td>
      <td><?= h($b['author']) ?></td>
      <td><div class="row-actions">
        <a class="btn btn-sm btn-ghost" href="<?= SELF ?>?edit=<?= (int)$b['id'] ?>">Edit</a>
        <form method="post" action="<?= SELF ?>" onsubmit="return confirm('Delete this blog post?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php admin_end();
