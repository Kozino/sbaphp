<?php
require __DIR__ . '/../private/bootstrap.php';
$item = one('SELECT * FROM blogs WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$item) not_found('That blog post does not exist or was removed.');
page_start($item['title'], 'blogs', $item['excerpt'] ?: snippet((string)$item['body'], 150));
?>
<section class="section"><article class="wrap narrow">
  <p><a href="/blogs.php">← All blogs</a></p>
  <p class="post-meta"><?= h(fmt_date($item['date'])) ?><?= $item['author'] ? ' · ' . h($item['author']) : '' ?></p>
  <h1><?= h($item['title']) ?></h1>
  <?php if ($item['image']): ?><img class="post-hero" src="<?= h(upload_url($item['image'])) ?>" alt=""><?php endif; ?>
  <div class="prose"><?= rich((string)$item['body']) ?></div>
</article></section>
<?php page_end();
