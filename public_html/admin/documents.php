<?php
require __DIR__ . '/_auth.php';
const SELF = '/admin/documents.php';

admin_action(SELF, function () {
    if (($_POST['action'] ?? '') === 'delete') {
        $d = one('SELECT * FROM documents WHERE id = ?', [(int)$_POST['id']]);
        if (!$d) throw new RuntimeException('Document not found.');
        q('DELETE FROM documents WHERE id = ?', [$d['id']]);
        delete_upload($d['filename'], document_dir());
        return 'Document deleted.';
    }
    $type = (string)($_POST['type'] ?? '');
    if (!array_key_exists($type, DOC_TYPES)) throw new RuntimeException('Invalid document type.');
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') throw new RuntimeException('Title is required.');
    $text = trim((string)($_POST['text'] ?? ''));
    $yearRaw = trim((string)($_POST['year'] ?? ''));
    $year = null;
    if ($yearRaw !== '') {
        $year = valid_year($yearRaw, 1990, 2100);
        if (!$year) throw new RuntimeException('Please enter a valid year.');
    }
    $file = save_upload('file', 'doc', document_dir(), DOC_FILE_TYPES);
    if (!$file && $text === '') throw new RuntimeException('Please upload a file or paste the text of the document.');
    q('INSERT INTO documents (type, year, title, filename, text) VALUES (?,?,?,?,?)', [$type, $year, $title, $file ?? '', $text]);
    return 'Document published to the Audit Portal.';
});

$rows = all('SELECT * FROM documents ORDER BY type ASC, year DESC, id DESC');

admin_start('Documents', 'documents', $ADMIN);
echo admin_head_flash();
?>
<div class="panel">
  <h2>Upload a document</h2>
  <p class="muted" style="margin-top:0">Documents appear in the Member Audit Portal for active members only. Accepted: PDF, Word, TXT (max 20 MB). You can also paste text instead of a file.</p>
  <form class="form" method="post" action="<?= SELF ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-row">
      <label>Type<select name="type"><?php foreach (DOC_TYPES as $k => $l) echo '<option value="' . h($k) . '">' . h($l) . '</option>'; ?></select></label>
      <label>Year<input type="number" name="year" min="1990" max="2100" placeholder="e.g. 2025"></label>
      <label>Title *<input name="title" required maxlength="255"></label>
    </div>
    <label>File<input type="file" name="file" accept=".pdf,.txt,.doc,.docx"></label>
    <label>Or paste the text <small>(optional)</small><textarea name="text" rows="5"></textarea></label>
    <div><button class="btn" type="submit">Publish document</button></div>
  </form>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>Type</th><th>Year</th><th>Title</th><th>Source</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows) echo empty_row(5, 'No documents yet.');
  foreach ($rows as $d): ?>
    <tr>
      <td><?= h(DOC_TYPES[$d['type']] ?? $d['type']) ?></td>
      <td><?= $d['year'] ? (int)$d['year'] : '—' ?></td>
      <td><a href="/file.php?doc=<?= (int)$d['id'] ?>" target="_blank"><?= h($d['title']) ?></a></td>
      <td><?= $d['filename'] ? badge('File', 'navy') : badge('Text', 'muted') ?></td>
      <td><form method="post" action="<?= SELF ?>" onsubmit="return confirm('Delete this document?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php admin_end();
