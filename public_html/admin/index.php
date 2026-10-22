<?php
require __DIR__ . '/_auth.php';
$pending = (int)val("SELECT COUNT(*) FROM members WHERE status='pending'");
$active = (int)val("SELECT COUNT(*) FROM members WHERE status='active'");
$msgs = (int)val('SELECT COUNT(*) FROM messages');
$owing = (float)val("SELECT COALESCE(SUM(GREATEST(expected - paid, 0)),0) FROM payments p JOIN members m ON m.id=p.member_id WHERE m.status='active'");
$recent = all("SELECT * FROM members WHERE status='pending' ORDER BY registered_at DESC LIMIT 6");
$msgList = all('SELECT * FROM messages ORDER BY created_at DESC LIMIT 5');

admin_start('Dashboard', 'dashboard', $ADMIN);
echo admin_head_flash();
?>
<div class="kpis">
  <a class="kpi" href="/admin/members.php?status=pending" style="text-decoration:none"><span>Awaiting approval</span><strong><?= $pending ?></strong></a>
  <a class="kpi" href="/admin/members.php?status=active" style="text-decoration:none"><span>Active members</span><strong><?= $active ?></strong></a>
  <a class="kpi" href="/admin/payments.php" style="text-decoration:none"><span>Outstanding dues</span><strong><?= h(fmt_money($owing)) ?></strong></a>
  <a class="kpi" href="/admin/messages.php" style="text-decoration:none"><span>Messages</span><strong><?= $msgs ?></strong></a>
</div>
<div class="grid-2">
  <div class="panel">
    <h2>New registrations</h2>
    <?php if ($recent): ?>
      <div class="table-wrap"><table><tbody>
      <?php foreach ($recent as $m): ?>
        <tr><td><a href="/admin/member.php?id=<?= (int)$m['id'] ?>"><?= h(full_name($m)) ?></a><br><span class="muted"><?= h($m['email']) ?></span></td>
            <td class="num"><?= h(fmt_date_short($m['registered_at'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="muted">No registrations waiting for approval.</p><?php endif; ?>
  </div>
  <div class="panel">
    <h2>Latest messages</h2>
    <?php if ($msgList): ?>
      <div class="table-wrap"><table><tbody>
      <?php foreach ($msgList as $m): ?>
        <tr><td><strong><?= h($m['subject']) ?></strong><br><span class="muted"><?= h($m['name']) ?> · <?= h($m['email']) ?></span></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="muted">No messages yet.</p><?php endif; ?>
  </div>
</div>
<div class="panel">
  <h2>Quick actions</h2>
  <div class="bulk-dl">
    <a class="btn btn-sm" href="/admin/news.php">Post news</a>
    <a class="btn btn-sm" href="/admin/gallery.php">Upload photos</a>
    <a class="btn btn-sm" href="/admin/documents.php">Upload document</a>
    <a class="btn btn-sm" href="/admin/tenures.php">Add past tenure</a>
    <a class="btn btn-sm" href="/admin/account.php">Financial report</a>
  </div>
</div>
<?php admin_end();
