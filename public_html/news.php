<?php
require __DIR__ . '/../private/bootstrap.php';
$rows = all('SELECT * FROM news ORDER BY date DESC, id DESC');
page_start('News', 'news', 'Latest news from the Savio Bosco Alphas 2012 association.');
page_head_block('News', 'Latest news from the association.', 'Updates');
?>
<section class="section"><div class="wrap">
  <?php if ($rows): ?><div class="grid"><?php foreach ($rows as $n) echo news_card($n); ?></div>
  <?php else: ?><p class="muted">No news has been posted yet.</p><?php endif; ?>
</div></section>
<?php page_end();
