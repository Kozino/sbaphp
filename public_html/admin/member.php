<?php
require __DIR__ . '/_auth.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$member = one('SELECT * FROM members WHERE id = ?', [$id]);
if (!$member) redirect('/admin/members.php');
$self = '/admin/member.php?id=' . $id;

// Upsert/remove a payment record exactly like the live site: both amounts 0 = remove
function save_payment(int $memberId, string $type, int $year, float $expected, float $paid, string $note): string
{
    if (!array_key_exists($type, PAYMENT_TYPES)) throw new RuntimeException('Invalid payment type.');
    if ($year < 2000 || $year > 2100) throw new RuntimeException('Please enter a valid year.');
    if ($expected < 0 || $paid < 0) throw new RuntimeException('Amounts cannot be negative.');
    if ($expected == 0 && $paid == 0) {
        q('DELETE FROM payments WHERE member_id = ? AND type = ? AND year = ?', [$memberId, $type, $year]);
        return 'Record removed.';
    }
    q('INSERT INTO payments (member_id, type, year, expected, paid, note) VALUES (?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE expected = VALUES(expected), paid = VALUES(paid), note = VALUES(note)',
        [$memberId, $type, $year, $expected, $paid, $note]);
    return 'Payment saved.';
}

admin_action($self, function () use ($id) {
    switch ($_POST['action'] ?? '') {
        case 'save_member':
            $f = [];
            foreach (['first_name', 'middle_name', 'last_name', 'email', 'phone', 'hometown', 'hometown_parish', 'residential_address', 'permanent_address'] as $k) {
                $f[$k] = trim((string)($_POST[$k] ?? ''));
            }
            if ($f['first_name'] === '' || $f['last_name'] === '') throw new RuntimeException('First name and last name are required.');
            if (!valid_email($f['email'])) throw new RuntimeException('Please enter a valid email address.');
            if (!valid_phone($f['phone'])) throw new RuntimeException('Please enter a valid phone number.');
            $dup = val('SELECT id FROM members WHERE email = ? AND id <> ?', [$f['email'], $id]);
            if ($dup) throw new RuntimeException('Another member already uses this email.');
            $status = (string)($_POST['status'] ?? '');
            if (!in_array($status, ['active', 'pending', 'rejected'], true)) throw new RuntimeException('Invalid status.');
            q('UPDATE members SET first_name=?, middle_name=?, last_name=?, email=?, phone=?, hometown=?, hometown_parish=?,
               residential_address=?, permanent_address=?, status=? WHERE id=?',
                [...array_values($f), $status, $id]);
            return 'Member details saved.';
        case 'payment':
            return save_payment(
                $id,
                (string)($_POST['type'] ?? ''),
                (int)($_POST['year'] ?? 0),
                (float)($_POST['expected'] ?? 0),
                (float)($_POST['paid'] ?? 0),
                trim((string)($_POST['note'] ?? ''))
            );
        case 'delete_payment':
            q('DELETE FROM payments WHERE id = ? AND member_id = ?', [(int)($_POST['payment_id'] ?? 0), $id]);
            return 'Payment record deleted.';
    }
    throw new RuntimeException('Unknown action.');
});

$payments = all('SELECT * FROM payments WHERE member_id = ? ORDER BY year DESC, type ASC', [$id]);
$m = $member;
admin_start(full_name($member), 'members', $ADMIN);
echo admin_head_flash();
?>
<p><a href="/admin/members.php">← All members</a></p>
<div class="grid-2" style="align-items:start">
  <div class="panel">
    <h2>Details <span class="muted" style="font-size:.9rem">· <code><?= h($m['unique_id']) ?></code></span></h2>
    <form class="form" method="post" action="<?= h($self) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_member">
      <div class="form-row">
        <label>First name<input name="first_name" required value="<?= h($m['first_name']) ?>"></label>
        <label>Middle name<input name="middle_name" value="<?= h($m['middle_name']) ?>"></label>
        <label>Last name<input name="last_name" required value="<?= h($m['last_name']) ?>"></label>
      </div>
      <div class="form-row">
        <label>Email<input type="email" name="email" required value="<?= h($m['email']) ?>"></label>
        <label>Phone<input name="phone" value="<?= h($m['phone']) ?>"></label>
      </div>
      <div class="form-row">
        <label>Hometown<input name="hometown" value="<?= h($m['hometown']) ?>"></label>
        <label>Hometown parish<input name="hometown_parish" value="<?= h($m['hometown_parish']) ?>"></label>
      </div>
      <label>Residential address<textarea name="residential_address" rows="2"><?= h($m['residential_address']) ?></textarea></label>
      <label>Permanent address<textarea name="permanent_address" rows="2"><?= h($m['permanent_address']) ?></textarea></label>
      <label>Status
        <select name="status">
          <?php foreach (['pending' => 'Pending', 'active' => 'Active', 'rejected' => 'Rejected'] as $k => $l): ?>
            <option value="<?= $k ?>" <?= $m['status'] === $k ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div><button class="btn" type="submit">Save details</button></div>
    </form>
  </div>

  <div class="panel">
    <h2>Record a payment</h2>
    <p class="muted" style="margin-top:0">Enter both amounts as 0 to remove a record.</p>
    <form class="form" method="post" action="<?= h($self) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="payment">
      <div class="form-row">
        <label>Type<select name="type"><?php foreach (PAYMENT_TYPES as $k => $l) echo '<option value="' . h($k) . '">' . h($l) . '</option>'; ?></select></label>
        <label>Year<input type="number" name="year" min="2000" max="2100" value="<?= current_year() ?>" required></label>
      </div>
      <div class="form-row">
        <label>Expected (₦)<input type="number" name="expected" min="0" step="0.01" value="0"></label>
        <label>Paid (₦)<input type="number" name="paid" min="0" step="0.01" value="0"></label>
      </div>
      <label>Note<input name="note" maxlength="500"></label>
      <div><button class="btn" type="submit">Save payment</button></div>
    </form>
  </div>
</div>

<div class="panel">
  <h2>Payment records</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Year</th><th>Type</th><th class="num">Expected</th><th class="num">Paid</th><th>Status</th><th>Note</th><th></th></tr></thead>
    <tbody>
    <?php if (!$payments) echo empty_row(7, 'No payment records yet.');
    foreach ($payments as $p): [$label, $tone] = payment_status((float)$p['expected'], (float)$p['paid']); ?>
      <tr>
        <td><?= (int)$p['year'] ?></td>
        <td><?= h(PAYMENT_TYPES[$p['type']] ?? $p['type']) ?></td>
        <td class="num"><?= h(fmt_money($p['expected'])) ?></td>
        <td class="num"><?= h(fmt_money($p['paid'])) ?></td>
        <td><?= badge($label, $tone) ?></td>
        <td><?= h($p['note']) ?></td>
        <td><form method="post" onsubmit="return confirm('Delete this payment record?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php admin_end();
