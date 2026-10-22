<?php
require __DIR__ . '/../private/bootstrap.php';

$members = (int)val("SELECT COUNT(*) FROM members WHERE status = 'active'");
$news = all('SELECT * FROM news ORDER BY date DESC, id DESC LIMIT 3');
$gallery = all('SELECT * FROM gallery ORDER BY id DESC LIMIT 4');

page_start('', 'home');
?>
<section class="hero">
  <div class="wrap">
    <p class="eyebrow">Savio Bosco Alphas 2012 · St. Dominic Savio Seminary, Akpu</p>
    <h1><?= h(APP_TAGLINE) ?></h1>
    <p class="lede">The official home of the class of 2012 — news, executives, gallery, and the member audit portal, all in one place.</p>
    <div class="row-actions" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px">
      <a class="btn btn-gold" href="/register.php">Register as a member</a>
      <a class="btn btn-ghost" href="/audit.php">Member Audit Portal</a>
    </div>
    <div class="stats">
      <div class="stat"><strong><?= $members ?></strong>Active members</div>
      <div class="stat"><strong><?= (int)val('SELECT COUNT(*) FROM gallery') ?></strong>Gallery photos</div>
      <div class="stat"><strong>2006</strong>Entered the seminary</div>
    </div>
    <p class="motto">“<?= h(APP_MOTTO) ?>” — <?= h(APP_MOTTO_TRANSLATION) ?> (<?= h(APP_MOTTO_REF) ?>)</p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="toolbar"><div><p class="eyebrow">Latest</p><h2>News from the association</h2></div><a href="/news.php">All news →</a></div>
    <?php if ($news): ?>
      <div class="grid"><?php foreach ($news as $n) echo news_card($n); ?></div>
    <?php else: ?>
      <p class="muted">No news has been posted yet.</p>
    <?php endif; ?>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="wrap">
    <div class="toolbar"><div><p class="eyebrow">Memories</p><h2>From the gallery</h2></div><a href="/gallery.php">View gallery →</a></div>
    <?php if ($gallery): ?>
      <div class="gallery-grid">
        <?php foreach ($gallery as $g): ?>
          <a href="/gallery.php"><img src="<?= h(upload_url($g['image'])) ?>" alt="<?= h($g['title']) ?>" loading="lazy"><div><strong><?= h($g['title']) ?></strong><br><span class="muted"><?= (int)$g['event_year'] ?></span></div></a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted">Photos will appear here once uploaded.</p>
    <?php endif; ?>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="wrap narrow card center" style="padding:28px">
    <h2>Part of the family?</h2>
    <p class="lede">Register your details to appear in the association roll. Once approved, your member ID unlocks the Audit Portal.</p>
    <p><a class="btn" href="/register.php">Register now</a> <a class="btn btn-ghost" href="/about.php">Read about us</a></p>
  </div>
</section>
<?php
page_end();
