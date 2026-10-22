<?php
require __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../private/reports.php';

admin_action('/admin/account.php?year=' . (int)($_POST['year'] ?? 0), function () {
    $act = $_POST['action'] ?? '';
    $year = valid_year($_POST['year'] ?? null, 2000, 2100);
    if (!$year) throw new RuntimeException('Please enter a valid year.');
    if ($act === 'add_year') {
        q('INSERT INTO account_years (year, report_note) VALUES (?, \'\') ON DUPLICATE KEY UPDATE year = year', [$year]);
        return 'Year ' . $year . ' is ready.';
    }
    if ($act === 'save_year') {
        $opening = (float)($_POST['opening_balance'] ?? 0);
        if (!is_finite($opening) || $opening < 0) throw new RuntimeException('Opening balance must be zero or more.');
        $note = substr(trim((string)($_POST['report_note'] ?? '')), 0, 4000);
        q('INSERT INTO account_years (year, opening_balance, financial_secretary, report_note) VALUES (?,?,?,?)
           ON DUPLICATE KEY UPDATE opening_balance=VALUES(opening_balance), financial_secretary=VALUES(financial_secretary), report_note=VALUES(report_note)',
            [$year, $opening, trim((string)($_POST['financial_secretary'] ?? '')), $note]);
        return 'Report settings saved for ' . $year . '.';
    }
    if ($act === 'add_entry') {
        $kind = (string)($_POST['kind'] ?? '');
        if (!in_array($kind, ['income', 'expense'], true)) throw new RuntimeException('Invalid entry type.');
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Please enter the name of the item.');
        $amount = (float)($_POST['amount'] ?? 0);
        if (!is_finite($amount) || $amount <= 0) throw new RuntimeException('Amount must be a positive number (in naira).');
        $date = (string)($_POST['entry_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) throw new RuntimeException('Please provide a valid date.');
        q('INSERT INTO account_years (year, report_note) VALUES (?, \'\') ON DUPLICATE KEY UPDATE year = year', [$year]);
        q('INSERT INTO account_entries (year, kind, name, amount, entry_date, note) VALUES (?,?,?,?,?,?)',
            [$year, $kind, $name, $amount, $date, trim((string)($_POST['note'] ?? ''))]);
        return 'Entry added.';
    }
    if ($act === 'update_entry') {
        $id = (int)($_POST['entry_id'] ?? 0);
        $kind = (string)($_POST['kind'] ?? '');
        if (!in_array($kind, ['income', 'expense'], true)) throw new RuntimeException('Invalid entry type.');
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Please enter the name of the item.');
        $amount = (float)($_POST['amount'] ?? 0);
        if (!is_finite($amount) || $amount <= 0) throw new RuntimeException('Amount must be a positive number (in naira).');
        $date = (string)($_POST['entry_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) throw new RuntimeException('Please provide a valid date.');
        q('UPDATE account_entries SET kind=?, name=?, amount=?, entry_date=?, note=? WHERE id=? AND year=?',
            [$kind, $name, $amount, $date, trim((string)($_POST['note'] ?? '')), $id, $year]);
        return 'Entry updated.';
    }
    if ($act === 'delete_entry') {
        q('DELETE FROM account_entries WHERE id = ? AND year = ?', [(int)($_POST['entry_id'] ?? 0), $year]);
        return 'Entry deleted.';
    }
    throw new RuntimeException('Unknown action.');
});

$years = array_map(fn($r) => (int)$r['year'], all('SELECT year FROM account_years ORDER BY year DESC'));
$year = valid_year($_GET['year'] ?? null, 2000, 2100) ?? ($years[0] ?? current_year());
if (!in_array($year, $years, true)) $years = array_values(array_unique(array_merge([$year], $years)));
rsort($years);
$yr = one('SELECT * FROM account_years WHERE year = ?', [$year]) ?? ['year' => $year, 'opening_balance' => 0, 'financial_secretary' => '', 'report_note' => ''];
$entries = all('SELECT * FROM account_entries WHERE year = ? ORDER BY entry_date ASC, id ASC', [$year]);
$sum = account_summary($yr, $entries);
$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? one('SELECT * FROM account_entries WHERE id = ? AND year = ?', [$editId, $year]) : null;
$ngn = fn($n) => fmt_money($n);

admin_start('Account & Financial Report', 'account', $ADMIN);
echo admin_head_flash();
?>
<div class="toolbar">
  <form method="get" action="/admin/account.php">
    <select name="year" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-sm" type="submit">Open</button></noscript>
  </form>
  <form method="post" action="/admin/account.php" class="bulk-dl">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_year">
    <input type="number" name="year" min="2000" max="2100" placeholder="New year e.g. <?= current_year() + 1 ?>" style="width:220px" required>
    <button class="btn btn-sm" type="submit">Add year</button>
  </form>
  <div class="bulk-dl">
    <a class="btn btn-sm btn-gold" href="/admin/account-export.php?year=<?= $year ?>&amp;format=xlsx">Download Excel</a>
    <a class="btn btn-sm btn-gold" href="/admin/account-export.php?year=<?= $year ?>&amp;format=pdf">Download PDF</a>
  </div>
</div>

<div class="kpis">
  <div class="kpi"><span>Balance brought forward</span><strong><?= h($ngn($sum['opening'])) ?></strong></div>
  <div class="kpi"><span>Total income</span><strong><?= h($ngn($sum['total_income'])) ?></strong></div>
  <div class="kpi"><span>Total expenditure</span><strong><?= h($ngn($sum['total_expense'])) ?></strong></div>
  <div class="kpi"><span>Balance carried forward</span><strong><?= h($ngn($sum['closing'])) ?></strong></div>
</div>

<div class="grid-2" style="align-items:start">
  <div class="panel">
    <h2>Report settings for <?= $year ?></h2>
    <form class="form" method="post" action="/admin/account.php?year=<?= $year ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_year"><input type="hidden" name="year" value="<?= $year ?>">
      <div class="form-row">
        <label>Opening balance (₦)<input type="number" name="opening_balance" min="0" step="0.01" value="<?= h($yr['opening_balance']) ?>"></label>
        <label>Financial Secretary (name on report)<input name="financial_secretary" value="<?= h($yr['financial_secretary']) ?>"></label>
      </div>
      <label>Note <small>(shown bold + italic at the end of the Excel and PDF, before “Prepared and submitted by”)</small>
        <textarea name="report_note" rows="3" maxlength="4000"><?= h($yr['report_note']) ?></textarea>
      </label>
      <div><button class="btn" type="submit">Save settings</button></div>
    </form>
  </div>

  <div class="panel">
    <h2><?= $edit ? 'Edit entry' : 'Add an entry' ?></h2>
    <form class="form" method="post" action="/admin/account.php?year=<?= $year ?><?= $edit ? '&amp;edit=' . (int)$edit['id'] : '' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="year" value="<?= $year ?>">
      <input type="hidden" name="action" value="<?= $edit ? 'update_entry' : 'add_entry' ?>">
      <?php if ($edit): ?><input type="hidden" name="entry_id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
      <div class="form-row">
        <label>Type<select name="kind"><option value="income" <?= ($edit['kind'] ?? 'income') === 'income' ? 'selected' : '' ?>>Income</option><option value="expense" <?= ($edit['kind'] ?? '') === 'expense' ? 'selected' : '' ?>>Expenditure</option></select></label>
        <label>Date<input type="date" name="entry_date" required value="<?= h($edit['entry_date'] ?? date('Y-m-d')) ?>"></label>
      </div>
      <div class="form-row">
        <label>Particulars<input name="name" required maxlength="255" value="<?= h($edit['name'] ?? '') ?>"></label>
        <label>Amount (₦)<input type="number" name="amount" min="0.01" step="0.01" required value="<?= h($edit['amount'] ?? '') ?>"></label>
      </div>
      <label>Note (optional)<input name="note" maxlength="500" value="<?= h($edit['note'] ?? '') ?>"></label>
      <div class="row-actions"><button class="btn" type="submit"><?= $edit ? 'Update entry' : 'Add entry' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="/admin/account.php?year=<?= $year ?>">Cancel</a><?php endif; ?></div>
    </form>
  </div>
</div>

<?php foreach (['income' => 'Income', 'expense' => 'Expenditure'] as $kind => $label):
    $rows = array_values(array_filter($entries, fn($e) => $e['kind'] === $kind)); ?>
  <div class="panel">
    <h2><?= $label ?> for <?= $year ?> <span class="muted" style="font-size:.9rem">· <?= count($rows) ?> record(s)</span></h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Date</th><th>Particulars</th><th>Note</th><th class="num">Amount</th><th></th></tr></thead>
      <tbody>
      <?php if (!$rows) echo empty_row(5, 'No ' . strtolower($label) . ' recorded for this year.');
      foreach ($rows as $e): ?>
        <tr>
          <td><?= h(fmt_date_short($e['entry_date'])) ?></td>
          <td><?= h($e['name']) ?></td>
          <td class="muted"><?= h($e['note']) ?></td>
          <td class="num"><?= h($ngn($e['amount'])) ?></td>
          <td><div class="row-actions">
            <a class="btn btn-sm btn-ghost" href="/admin/account.php?year=<?= $year ?>&amp;edit=<?= (int)$e['id'] ?>">Edit</a>
            <form method="post" action="/admin/account.php?year=<?= $year ?>" onsubmit="return confirm('Delete this entry?')"><?= csrf_field() ?><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="action" value="delete_entry"><input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      <tr class="total"><td colspan="3">Total <?= strtolower($label) ?></td><td class="num"><?= h($ngn(array_sum(array_map(fn($e) => (float)$e['amount'], $rows)))) ?></td><td></td></tr>
      </tbody>
    </table></div>
  </div>
<?php endforeach;
admin_end();
