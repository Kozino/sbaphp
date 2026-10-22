<?php
require __DIR__ . '/_auth.php';

admin_action('/admin/members.php', function () {
    $id = (int)($_POST['id'] ?? 0);
    $m = one('SELECT * FROM members WHERE id = ?', [$id]);
    if (!$m) throw new RuntimeException('Member not found.');
    switch ($_POST['action'] ?? '') {
        case 'status':
            $status = (string)($_POST['status'] ?? '');
            if (!in_array($status, ['active', 'pending', 'rejected'], true)) throw new RuntimeException('Invalid status.');
            q('UPDATE members SET status = ? WHERE id = ?', [$status, $id]);
            return 'Status set to ' . $status . ' for ' . full_name($m) . '.';
        case 'delete':
            q('DELETE FROM members WHERE id = ?', [$id]);
            return 'Member deleted.';
    }
    throw new RuntimeException('Unknown action.');
});

$status = param('status');
$search = param('q');
$where = [];
$params = [];
if (in_array($status, ['pending', 'active', 'rejected'], true)) {
    $where[] = 'm.status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $where[] = '(m.unique_id LIKE ? OR m.email LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
$sql = "SELECT m.*,
          (SELECT COALESCE(SUM(p.paid),0) FROM payments p WHERE p.member_id = m.id) AS total_paid,
          (SELECT COALESCE(SUM(GREATEST(p.expected - p.paid, 0)),0) FROM payments p WHERE p.member_id = m.id) AS total_owing
        FROM members m" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY m.registered_at DESC, m.id DESC';
$rows = all($sql, $params);

admin_start('Members', 'members', $ADMIN);
echo admin_head_flash();
?>
<div class="toolbar">
  <form method="get" action="/admin/members.php">
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach (['pending' => 'Pending approval', 'active' => 'Active', 'rejected' => 'Rejected'] as $k => $l): ?>
        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <input name="q" value="<?= h($search) ?>" placeholder="Search name, email or ID">
    <button class="btn btn-sm" type="submit">Filter</button>
  </form>
  <span class="muted"><?= count($rows) ?> member(s)</span>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>Member ID</th><th>Name</th><th>Contact</th><th>Hometown</th><th>Status</th><th class="num">Paid</th><th class="num">Owing</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (!$rows) echo empty_row(8, 'No members match this filter.');
  foreach ($rows as $m): ?>
    <tr>
      <td><code><?= h($m['unique_id']) ?></code></td>
      <td><a href="/admin/member.php?id=<?= (int)$m['id'] ?>"><strong><?= h(full_name($m)) ?></strong></a><br><span class="muted"><?= h(fmt_date_short($m['registered_at'])) ?></span></td>
      <td><?= h($m['email']) ?><br><span class="muted"><?= h($m['phone']) ?></span></td>
      <td><?= h($m['hometown']) ?></td>
      <td><?= badge(ucfirst($m['status']), ['active' => 'ok', 'pending' => 'warn', 'rejected' => 'bad'][$m['status']] ?? 'muted') ?></td>
      <td class="num"><?= h(fmt_money($m['total_paid'])) ?></td>
      <td class="num"><?= h(fmt_money($m['total_owing'])) ?></td>
      <td>
        <div class="row-actions">
          <?php if ($m['status'] !== 'active'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="status" value="active"><button class="btn btn-sm btn-gold" type="submit">Approve</button></form>
          <?php endif; ?>
          <?php if ($m['status'] !== 'rejected'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-ghost" type="submit">Reject</button></form>
          <?php endif; ?>
          <a class="btn btn-sm btn-ghost" href="/admin/member.php?id=<?= (int)$m['id'] ?>">Open</a>
          <form method="post" onsubmit="return confirm('Delete this member and all their payment records? This cannot be undone.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php admin_end();
