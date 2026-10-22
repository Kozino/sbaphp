<?php
require __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../private/reports.php';

admin_action('/admin/payments.php', function () {
    $uid = strtoupper(trim((string)($_POST['member_id'] ?? '')));
    $m = one('SELECT id FROM members WHERE unique_id = ?', [$uid]);
    if (!$m) throw new RuntimeException('No member has that ID.');
    $type = (string)($_POST['type'] ?? '');
    if (!array_key_exists($type, PAYMENT_TYPES)) throw new RuntimeException('Invalid payment type.');
    $year = (int)($_POST['year'] ?? 0);
    if ($year < 2000 || $year > 2100) throw new RuntimeException('Please enter a valid year.');
    $expected = (float)($_POST['expected'] ?? 0);
    $paid = (float)($_POST['paid'] ?? 0);
    if ($expected < 0 || $paid < 0) throw new RuntimeException('Amounts cannot be negative.');
    if ($expected == 0 && $paid == 0) {
        q('DELETE FROM payments WHERE member_id=? AND type=? AND year=?', [$m['id'], $type, $year]);
        return 'Record removed.';
    }
    q('INSERT INTO payments (member_id, type, year, expected, paid, note) VALUES (?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE expected=VALUES(expected), paid=VALUES(paid), note=VALUES(note)',
        [$m['id'], $type, $year, $expected, $paid, trim((string)($_POST['note'] ?? ''))]);
    return 'Payment saved for ' . $uid . '.';
});

$member = strtoupper(param('member'));
$year = valid_year(param('year'), 2000, 2100);
$type = param('type');
$status = param('status');

$where = [];
$params = [];
if ($member !== '') { $where[] = 'm.unique_id = ?'; $params[] = $member; }
if ($year) { $where[] = 'p.year = ?'; $params[] = $year; }
if (array_key_exists($type, PAYMENT_TYPES)) { $where[] = 'p.type = ?'; $params[] = $type; }
$rows = all(
    'SELECT p.*, m.first_name, m.middle_name, m.last_name, m.unique_id FROM payments p
     JOIN members m ON m.id = p.member_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
    ' ORDER BY m.last_name, m.first_name, p.year DESC, p.type',
    $params
);
// Status is computed (same rules as the public site), so it is filtered in PHP.
$rows = array_values(array_filter($rows, function ($p) use ($status) {
    if ($status === '') return true;
    return payment_status((float)$p['expected'], (float)$p['paid'])[0] === ['paid' => 'Paid', 'partial' => 'Partially Paid', 'owing' => 'Owing', 'contribution' => 'Contribution', 'none' => 'No Record'][$status];
}));

$totals = ['expected' => 0.0, 'paid' => 0.0];
foreach ($rows as $p) { $totals['expected'] += (float)$p['expected']; $totals['paid'] += (float)$p['paid']; }

// Export (filtered view)
$export = param('export');
if (in_array($export, ['xlsx', 'csv'], true)) {
    $lines = [['Member ID', 'Last name', 'First name', 'Middle name', 'Type', 'Year', 'Expected (NGN)', 'Paid (NGN)', 'Outstanding (NGN)', 'Status', 'Note']];
    foreach ($rows as $p) {
        $out = max(0, (float)$p['expected'] - (float)$p['paid']);
        $lines[] = [$p['unique_id'], $p['last_name'], $p['first_name'], $p['middle_name'], PAYMENT_TYPES[$p['type']] ?? $p['type'], (int)$p['year'],
            (float)$p['expected'], (float)$p['paid'], $out, payment_status((float)$p['expected'], (float)$p['paid'])[0], $p['note']];
    }
    $stamp = date('Ymd-His');
    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sba12-payments-' . $stamp . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        foreach ($lines as $l) fputcsv($out, $l, ',', '"', '\\');
        fclose($out);
        exit;
    }
    $sheetRows = [];
    foreach ($lines as $i => $l) {
        $row = [];
        foreach ($l as $j => $v) {
            $row[] = $i === 0 ? ['v' => $v, 's' => 'header'] : (is_float($v) ? ['v' => $v, 's' => 'money'] : ['v' => $v, 's' => 'text']);
        }
        $sheetRows[] = $row;
    }
    $bin = xlsx_build([[
        'name' => 'Payments', 'cols' => [14, 18, 18, 14, 22, 8, 16, 16, 18, 16, 40], 'freeze' => true,
        'rows' => $sheetRows, 'heights' => [1 => 20],
    ]]);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="sba12-payments-' . $stamp . '.xlsx"');
    header('Content-Length: ' . strlen($bin));
    echo $bin;
    exit;
}

$qs = http_build_query(array_filter(['member' => $member, 'year' => $year ?: '', 'type' => $type, 'status' => $status]));
admin_start('Payments', 'payments', $ADMIN);
echo admin_head_flash();
?>
<div class="panel">
  <h2>Record a payment</h2>
  <form class="form" method="post" action="/admin/payments.php">
    <?= csrf_field() ?>
    <div class="form-row">
      <label>Member ID<input name="member_id" required maxlength="8" style="text-transform:uppercase" placeholder="SBA12XXX"></label>
      <label>Type<select name="type"><?php foreach (PAYMENT_TYPES as $k => $l) echo '<option value="' . h($k) . '">' . h($l) . '</option>'; ?></select></label>
      <label>Year<input type="number" name="year" min="2000" max="2100" value="<?= current_year() ?>" required></label>
      <label>Expected (₦)<input type="number" name="expected" min="0" step="0.01" value="0"></label>
      <label>Paid (₦)<input type="number" name="paid" min="0" step="0.01" value="0"></label>
      <label>Note<input name="note" maxlength="500"></label>
    </div>
    <div><button class="btn" type="submit">Save payment</button> <span class="muted">Enter 0 and 0 to remove a record.</span></div>
  </form>
</div>

<div class="toolbar">
  <form method="get" action="/admin/payments.php">
    <input name="member" value="<?= h($member) ?>" placeholder="Member ID" style="text-transform:uppercase;width:140px">
    <input type="number" name="year" value="<?= h($year ?: '') ?>" placeholder="Year" min="2000" max="2100" style="width:110px">
    <select name="type"><option value="">All types</option><?php foreach (PAYMENT_TYPES as $k => $l) echo '<option value="' . h($k) . '"' . ($type === $k ? ' selected' : '') . '>' . h($l) . '</option>'; ?></select>
    <select name="status"><option value="">Any status</option><?php foreach (['paid' => 'Paid', 'partial' => 'Partially Paid', 'owing' => 'Owing', 'contribution' => 'Contribution', 'none' => 'No Record'] as $k => $l) echo '<option value="' . $k . '"' . ($status === $k ? ' selected' : '') . '>' . $l . '</option>'; ?></select>
    <button class="btn btn-sm" type="submit">Filter</button>
    <a class="btn btn-sm btn-ghost" href="/admin/payments.php">Clear</a>
  </form>
  <div class="bulk-dl">
    <a class="btn btn-sm btn-gold" href="/admin/payments.php?<?= h($qs) ?>&amp;export=xlsx">Export Excel</a>
    <a class="btn btn-sm btn-ghost" href="/admin/payments.php?<?= h($qs) ?>&amp;export=csv">Export CSV</a>
  </div>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>Member</th><th>ID</th><th>Year</th><th>Type</th><th class="num">Expected</th><th class="num">Paid</th><th>Status</th><th>Note</th></tr></thead>
  <tbody>
  <?php if (!$rows) echo empty_row(8, 'No payment records match this filter.');
  foreach ($rows as $p): [$label, $tone] = payment_status((float)$p['expected'], (float)$p['paid']); ?>
    <tr>
      <td><a href="/admin/member.php?id=<?= (int)$p['member_id'] ?>"><?= h(trim($p['last_name'] . ' ' . $p['first_name'])) ?></a></td>
      <td><code><?= h($p['unique_id']) ?></code></td>
      <td><?= (int)$p['year'] ?></td>
      <td><?= h(PAYMENT_TYPES[$p['type']] ?? $p['type']) ?></td>
      <td class="num"><?= h(fmt_money($p['expected'])) ?></td>
      <td class="num"><?= h(fmt_money($p['paid'])) ?></td>
      <td><?= badge($label, $tone) ?></td>
      <td><?= h($p['note']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if ($rows): ?>
    <tr class="total"><td colspan="4">Total (<?= count($rows) ?> record(s))</td><td class="num"><?= h(fmt_money($totals['expected'])) ?></td><td class="num"><?= h(fmt_money($totals['paid'])) ?></td><td colspan="2"></td></tr>
  <?php endif; ?>
  </tbody>
</table></div>
<?php admin_end();
