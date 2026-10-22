<?php
require __DIR__ . '/../private/bootstrap.php';
$years = all('SELECT DISTINCT event_year FROM gallery ORDER BY event_year DESC');
$year = valid_year($_GET['year'] ?? null, 1990, 2200);
$rows = $year
    ? all('SELECT * FROM gallery WHERE event_year = ? ORDER BY id DESC', [$year])
    : all('SELECT * FROM gallery ORDER BY event_year DESC, id DESC');
page_start('Gallery', 'gallery', 'Photos from the seminary, reunions and association events.');
page_head_block('Gallery', 'Photos from the seminary, reunions and association events.', 'Memories');
?>
<section class="section"><div class="wrap">
  <?php if ($years): ?>
    <nav class="years" aria-label="Filter by year">
      <a href="/gallery.php" class="<?= $year ? '' : 'on' ?>">All years</a>
      <?php foreach ($years as $y): ?>
        <a href="/gallery.php?year=<?= (int)$y['event_year'] ?>" class="<?= $year === (int)$y['event_year'] ? 'on' : '' ?>"><?= (int)$y['event_year'] ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <?php if ($rows): ?>
    <div class="gallery-grid">
      <?php foreach ($rows as $g): ?>
        <a href="<?= h(upload_url($g['image'])) ?>" target="_blank" rel="noopener">
          <img src="<?= h(upload_url($g['image'])) ?>" alt="<?= h($g['title']) ?>" loading="lazy">
          <div><strong><?= h($g['title']) ?></strong><br><span class="muted"><?= h($g['caption']) ?> <?= (int)$g['event_year'] ?></span></div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?><p class="muted">No photos yet<?= $year ? ' for ' . $year : '' ?>.</p><?php endif; ?>
</div></section>
<?php page_end();
