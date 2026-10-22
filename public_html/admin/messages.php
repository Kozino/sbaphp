<?php
require __DIR__ . '/_auth.php';

admin_action('/admin/messages.php', function () {
    if (($_POST['action'] ?? '') !== 'delete') throw new RuntimeException('Unknown action.');
    q('DELETE FROM messages WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    return 'Message deleted.';
});
$rows = all('SELECT * FROM messages ORDER BY created_at DESC, id DESC');

admin_start('Messages', 'messages', $ADMIN);
echo admin_head_flash();
?>
<div class="table-wrap"><table>
  <thead><tr><th>Received</th><th>From</th><th>Subject</th><th>Message</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows) echo empty_row(5, 'No messages yet.');
  foreach ($rows as $m): ?>
    <tr>
      <td><?= h(fmt_date_short($m['created_at'])) ?></td>
      <td><strong><?= h($m['name']) ?></strong><br><a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a><?= $m['phone'] ? '<br><span class="muted">' . h($m['phone']) . '</span>' : '' ?></td>
      <td><?= h($m['subject']) ?></td>
      <td style="white-space:pre-wrap;max-width:420px"><?= h($m['message']) ?></td>
      <td><form method="post" action="/admin/messages.php" onsubmit="return confirm('Delete this message?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php admin_end();
