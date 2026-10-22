<?php
require __DIR__ . '/../private/bootstrap.php';
$rows = all('SELECT * FROM blogs ORDER BY date DESC, id DESC');
page_start('Blogs', 'blogs', 'Reflections and stories from members of the Savio Bosco Alphas 2012 association.');
page_head_block('Blogs', 'Reflections and stories from members of the association.', 'Stories');
?>
<section class="section"><div class="wrap">
  <?php if ($rows): ?><div class="grid"><?php foreach ($rows as $b) echo blog_card($b); ?></div>
  <?php else: ?><p class="muted">No posts yet.</p><?php endif; ?>
</div></section>
<?php page_end();
