<?php
require __DIR__ . '/../private/bootstrap.php';

/*
 * Member Audit Portal. The member ID is sent by POST and kept in the
 * session (never placed in a URL). Only ACTIVE members see their record.
 */
$error = null;
if (isset($_GET['logout'])) {
    unset($_SESSION['audit_member_id']);
    redirect('/audit.php');
}
if (is_post()) {
    require_csrf();
    $id = strtoupper(trim((string)($_POST['member_id'] ?? '')));
    if ($id === '') {
        $error = 'Please provide a member ID.';
    } elseif (!preg_match('/^SBA12[A-HJ-NP-Z2-9]{3}$/', $id)) {
        $error = 'This ID is not in a valid format. It should be 8 characters, starting with SBA12.';
    } else {
        $m = one('SELECT * FROM members WHERE unique_id = ?', [$id]);
        if (!$m) {
            $error = 'No member was found with that ID. Please check your member ID and try again.';
        } elseif ($m['status'] === 'pending') {
            $error = "Your registration is still awaiting approval by the association's executives. Your member ID will become active on the Audit Portal once your registration has been approved. Please try again later or contact the association.";
        } elseif ($m['status'] === 'rejected') {
            $error = "This registration has not been approved by the association. Please contact the association's secretariat for assistance.";
        } else {
            session_regenerate_id(true);
            $_SESSION['audit_member_id'] = (int)$m['id'];
            redirect('/audit.php');
        }
    }
}

$member = null;
if (!empty($_SESSION['audit_member_id'])) {
    $member = one("SELECT * FROM members WHERE id = ? AND status = 'active'", [(int)$_SESSION['audit_member_id']]);
    if (!$member) unset($_SESSION['audit_member_id']);
}

page_start('Member Audit Portal', 'audit', 'Check your membership record, dues and documents with your member ID.');
page_head_block('Member Audit Portal', 'Enter your member ID to view your dues, contributions and the association’s documents.', 'Members only');
?>
<section class="section"><div class="wrap">
<?php if (!$member): ?>
  <div class="narrow audit-box">
    <?= alert_html($error) ?>
    <form class="form" method="post" action="/audit.php" novalidate>
      <?= csrf_field() ?>
      <label>Member ID <small>(e.g. SBA12K7Q)</small>
        <input name="member_id" required maxlength="8" style="text-transform:uppercase;letter-spacing:.1em" autocomplete="off" autocapitalize="characters" placeholder="SBA12XXX">
      </label>
      <div><button class="btn" type="submit">View my record</button></div>
    </form>
    <p class="muted" style="margin-top:14px">Don’t have an ID yet? <a href="/register.php">Register as a member</a>.</p>
  </div>
<?php else:
    $payments = all('SELECT * FROM payments WHERE member_id = ? ORDER BY year DESC, type ASC', [$member['id']]);
    $tot = ['expected' => 0.0, 'paid' => 0.0, 'owing' => 0.0];
    foreach ($payments as $p) {
        $tot['expected'] += (float)$p['expected'];
        $tot['paid'] += (float)$p['paid'];
        $tot['owing'] += max(0, (float)$p['expected'] - (float)$p['paid']);
    }
    $docs = all('SELECT * FROM documents ORDER BY type ASC, year DESC, id DESC');
?>
  <div class="grid-2" style="align-items:start">
    <div class="member-card">
      <span>Member ID</span>
      <span class="id"><?= h($member['unique_id']) ?></span>
      <strong style="font-size:1.2rem"><?= h(full_name($member)) ?></strong>
      <span><?= h($member['hometown']) ?><?= $member['hometown_parish'] ? ' · ' . h($member['hometown_parish']) : '' ?></span>
      <span>Registered <?= h(fmt_date($member['registered_at'])) ?></span>
    </div>
    <div class="card">
      <div class="kpis" style="margin:0">
        <div class="kpi"><span>Expected</span><strong><?= h(fmt_money($tot['expected'])) ?></strong></div>
        <div class="kpi"><span>Paid</span><strong><?= h(fmt_money($tot['paid'])) ?></strong></div>
        <div class="kpi"><span>Outstanding</span><strong style="color:<?= $tot['owing'] > 0 ? 'var(--bad)' : 'var(--ok)' ?>"><?= h(fmt_money($tot['owing'])) ?></strong></div>
      </div>
      <p class="muted" style="margin:10px 0 0">Keep your annual due current to vote, be voted for, and enjoy benevolent benefits.</p>
    </div>
  </div>

  <div class="panel" style="margin-top:18px">
    <h2>My payments</h2>
    <?php if ($payments): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Year</th><th>Type</th><th class="num">Expected</th><th class="num">Paid</th><th>Status</th><th>Note</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): [$label, $tone] = payment_status((float)$p['expected'], (float)$p['paid']); ?>
        <tr>
          <td><?= (int)$p['year'] ?></td>
          <td><?= h(PAYMENT_TYPES[$p['type']] ?? $p['type']) ?></td>
          <td class="num"><?= h(fmt_money($p['expected'])) ?></td>
          <td class="num"><?= h(fmt_money($p['paid'])) ?></td>
          <td><?= badge($label, $tone) ?></td>
          <td><?= h($p['note']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php else: ?><p class="muted">No payment records yet.</p><?php endif; ?>
  </div>

  <div class="panel">
    <h2>Documents</h2>
    <?php if ($docs): ?>
      <div class="doc-list">
      <?php foreach ($docs as $d): ?>
        <a href="/file.php?doc=<?= (int)$d['id'] ?>" target="_blank" rel="noopener">
          <span><?= h($d['title']) ?></span>
          <span class="muted"><?= h(DOC_TYPES[$d['type']] ?? $d['type']) ?><?= $d['year'] ? ' · ' . (int)$d['year'] : '' ?></span>
        </a>
      <?php endforeach; ?>
      </div>
    <?php else: ?><p class="muted">No documents have been published yet.</p><?php endif; ?>
  </div>
  <p><a href="/audit.php?logout=1">Sign out of the Audit Portal</a></p>
<?php endif; ?>
</div></section>
<?php page_end();
