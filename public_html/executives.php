<?php
require __DIR__ . '/../private/bootstrap.php';

$current = all('SELECT id, name, position, image FROM executives WHERE tenure_id IS NULL ORDER BY sort_order, id');
$tenures = all('SELECT id, start_year, end_year FROM executive_tenures ORDER BY end_year DESC, id DESC');
$past = [];
if ($tenures) {
    $rows = all('SELECT id, name, position, image, tenure_id FROM executives WHERE tenure_id IS NOT NULL ORDER BY sort_order, id');
    foreach ($tenures as $t) {
        $past[] = $t + ['executives' => array_values(array_filter($rows, fn($r) => (int)$r['tenure_id'] === (int)$t['id']))];
    }
}

page_start('Executives', 'executives', 'The current executive officers and past executives of the Savio Bosco Alphas 2012 association.');
page_head_block('Executives', 'The officers who lead the association today, and those who served before us.', 'Leadership');
?>
<section class="section"><div class="wrap">
  <?php if ($past): ?><h2>Current Executives</h2><?php endif; ?>
  <?php if ($current): ?>
    <div class="exec-grid"><?php foreach ($current as $e) echo exec_card($e); ?></div>
  <?php else: ?>
    <p class="muted">The current executives will be listed here.</p>
  <?php endif; ?>

  <?php if ($past): ?>
    <div class="tenure-section" style="margin-top:34px">
      <p class="eyebrow">Past Executives</p>
      <h2 style="margin-top:0">Those who served before us</h2>
    </div>
    <?php foreach ($past as $t): ?>
      <div class="tenure">
        <div class="tenure-head"><h2><?= (int)$t['start_year'] ?> – <?= (int)$t['end_year'] ?></h2><span class="muted"><?= count($t['executives']) ?> officer(s)</span></div>
        <?php if ($t['executives']): ?>
          <div class="exec-grid"><?php foreach ($t['executives'] as $e) echo exec_card($e); ?></div>
        <?php else: ?>
          <p class="muted">No officers recorded for this tenure.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div></section>
<?php page_end();
