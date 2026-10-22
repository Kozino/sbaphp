<?php
declare(strict_types=1);

/** Public site nav. $active = key of the current page. */
const PUBLIC_NAV = [
    'home' => ['Home', '/'],
    'about' => ['About', '/about.php'],
    'executives' => ['Executives', '/executives.php'],
    'news' => ['News', '/news.php'],
    'blogs' => ['Blogs', '/blogs.php'],
    'gallery' => ['Gallery', '/gallery.php'],
    'audit' => ['Audit Portal', '/audit.php'],
    'register' => ['Register', '/register.php'],
    'contact' => ['Contact', '/contact.php'],
];

const APP_TABS = [
    'home' => ['Home', '/', 'M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z'],
    'news' => ['News', '/news.php', 'M4 5h13v14H5a1 1 0 0 1-1-1zM17 8h3v10a2 2 0 0 1-2 2M7 9h7M7 12h7M7 15h5'],
    'gallery' => ['Gallery', '/gallery.php', 'M3 5h18v14H3zM3 16l5-5 4 4 3-3 6 6'],
    'audit' => ['Audit', '/audit.php', 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM9 12l2 2 4-4'],
    'more' => ['Menu', '#menu', 'M4 6h16M4 12h16M4 18h16'],
];

function page_start(string $title = '', string $active = '', string $desc = ''): void
{
    $s = settings();
    $pageTitle = $title !== '' ? $title . ' | ' . APP_NAME : APP_NAME;
    $description = $desc !== '' ? $desc : APP_TAGLINE . ' Alumni association of St. Dominic Savio Seminary, Akpu.';
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= h($pageTitle) ?></title>
  <meta name="description" content="<?= h($description) ?>">
  <meta name="theme-color" content="#001D69">
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="icon" type="image/png" href="/assets/img/icon-192.png">
  <link rel="apple-touch-icon" href="/assets/img/icon-192.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="SBA 2012">
  <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="wrap header-row">
    <a class="brand" href="/">
      <img src="/assets/img/logo.png" alt="" width="40" height="40">
      <span><strong>Savio Bosco Alphas</strong><small>2012 Class · Akpu</small></span>
    </a>
    <button class="nav-toggle" type="button" aria-controls="site-nav" aria-expanded="false" data-nav-toggle>
      <span></span><span></span><span></span><span class="sr">Menu</span>
    </button>
    <nav id="site-nav" class="site-nav" aria-label="Main">
      <?php foreach (PUBLIC_NAV as $key => [$label, $href]): ?>
        <a href="<?= h($href) ?>"<?= $active === $key ? ' class="on" aria-current="page"' : '' ?>><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<main id="main" class="site-main">
    <?php
}

function page_end(): void
{
    $s = settings();
    ?>
</main>

<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <p class="footer-brand">Savio Bosco Alphas 2012</p>
      <p class="muted"><?= h(APP_MOTTO) ?><br><em><?= h(APP_MOTTO_TRANSLATION) ?> — <?= h(APP_MOTTO_REF) ?></em></p>
    </div>
    <div>
      <p class="footer-head">Contact</p>
      <p><a href="mailto:<?= h($s['contact_email']) ?>"><?= h($s['contact_email']) ?></a></p>
      <p><?= h($s['contact_phone']) ?></p>
      <p class="muted"><?= h($s['contact_address']) ?></p>
    </div>
    <div>
      <p class="footer-head">Explore</p>
      <p><a href="/executives.php">Executives</a> · <a href="/news.php">News</a> · <a href="/gallery.php">Gallery</a></p>
      <p><a href="/audit.php">Member Audit Portal</a> · <a href="/register.php">Register</a></p>
      <p><a href="/admin/login.php">Admin sign in</a></p>
    </div>
  </div>
  <div class="wrap copy">© <?= date('Y') ?> Savio Bosco Alphas 2012. All rights reserved.
    <button class="link-btn" type="button" data-install hidden>Install the app</button>
  </div>
</footer>

<nav class="app-tabs" aria-label="App">
  <?php foreach (APP_TABS as $key => [$label, $href, $icon]): ?>
    <a href="<?= h($href) ?>" data-tab="<?= h($key) ?>"<?= $key === 'more' ? ' data-nav-toggle-tab' : '' ?>>
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= h($icon) ?>"/></svg>
      <span><?= h($label) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<div class="chat" data-chat>
  <button class="chat-fab" type="button" data-chat-open aria-label="Ask the association assistant">Ask us</button>
  <div class="chat-panel" hidden>
    <div class="chat-head"><strong>Association assistant</strong><button type="button" data-chat-close aria-label="Close">×</button></div>
    <div class="chat-log" data-chat-log><p class="muted">Ask about the constitution, executives, news or events.</p></div>
    <form class="chat-form" data-chat-form>
      <input name="message" maxlength="500" placeholder="Type your question…" required>
      <button type="submit">Send</button>
    </form>
  </div>
</div>

<script src="/assets/js/app.js" defer></script>
</body>
</html>
    <?php
}

/* ---------------- Admin shell ---------------- */

const ADMIN_NAV = [
    ['dashboard', 'Dashboard', '/admin/index.php'],
    ['members', 'Members', '/admin/members.php'],
    ['payments', 'Payments', '/admin/payments.php'],
    ['account', 'Account', '/admin/account.php'],
    ['news', 'News', '/admin/news.php'],
    ['blogs', 'Blogs', '/admin/blogs.php'],
    ['gallery', 'Gallery', '/admin/gallery.php'],
    ['documents', 'Documents', '/admin/documents.php'],
    ['executives', 'Executives', '/admin/executives.php'],
    ['tenures', 'Past Executives', '/admin/tenures.php'],
    ['messages', 'Messages', '/admin/messages.php'],
    ['settings', 'Settings', '/admin/settings.php'],
    ['password', 'Change password', '/admin/password.php'],
];

function admin_start(string $title, string $active, array $user): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= h($title) ?> · Admin · <?= h(APP_SHORT) ?></title>
  <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="admin">
<div class="admin-shell">
  <aside class="admin-side" id="admin-side">
    <a class="brand" href="/admin/index.php"><img src="/assets/img/logo.png" alt="" width="36" height="36"><span><strong>SBA 2012</strong><small>Admin</small></span></a>
    <nav>
      <?php foreach (ADMIN_NAV as [$key, $label, $href]): ?>
        <a href="<?= h($href) ?>"<?= $active === $key ? ' class="on"' : '' ?>><?= h($label) ?></a>
      <?php endforeach; ?>
      <a href="/" target="_blank" rel="noopener">View website ↗</a>
      <a href="/admin/logout.php">Sign out</a>
    </nav>
  </aside>
  <div class="admin-main">
    <header class="admin-top">
      <button class="nav-toggle admin-toggle" type="button" data-admin-toggle aria-label="Menu"><span></span><span></span><span></span></button>
      <h1><?= h($title) ?></h1>
      <span class="muted">Signed in as <?= h($user['name'] ?: $user['username']) ?></span>
    </header>
    <div class="admin-body">
    <?php
}

function admin_end(): void
{
    ?>
    </div>
  </div>
</div>
<script src="/assets/js/app.js" defer></script>
</body>
</html>
    <?php
}

/** Simple table/markup helper: status badge */
function badge(string $label, string $tone = 'muted'): string
{
    return '<span class="badge badge-' . h($tone) . '">' . h($label) . '</span>';
}

function empty_row(int $cols, string $msg = 'Nothing here yet.'): string
{
    return '<tr><td colspan="' . $cols . '" class="muted center">' . h($msg) . '</td></tr>';
}

/** Pagination-free "page not found" for public pages */
function not_found(string $msg = 'We could not find that page.'): void
{
    http_response_code(404);
    page_start('Not found', '');
    echo '<section class="wrap narrow section"><h1>Not found</h1><p>' . h($msg) . '</p><p><a class="btn" href="/">Back to home</a></p></section>';
    page_end();
    exit;
}

/* ---------------- Shared public helpers ---------------- */

function upload_url(?string $name, string $fallback = '/assets/img/logo.png'): string
{
    $name = basename((string)$name);
    return ($name !== '' && is_file(upload_dir() . '/' . $name)) ? '/uploads/' . rawurlencode($name) : $fallback;
}

function page_head_block(string $title, string $lede = '', string $eyebrow = ''): void
{
    echo '<section class="page-head"><div class="wrap">';
    if ($eyebrow !== '') echo '<p class="eyebrow">' . h($eyebrow) . '</p>';
    echo '<h1>' . h($title) . '</h1>';
    if ($lede !== '') echo '<p>' . h($lede) . '</p>';
    echo '</div></section>';
}

function news_card(array $n): string
{
    $url = '/news-detail.php?id=' . (int)$n['id'];
    return '<article class="card"><a href="' . h($url) . '"><img class="cover" src="' . h(upload_url($n['image'])) . '" alt="" loading="lazy"></a>'
        . '<p class="meta">' . h(fmt_date($n['date'])) . '</p>'
        . '<h3><a href="' . h($url) . '">' . h($n['title']) . '</a></h3>'
        . '<p class="muted">' . h(snippet((string)$n['body'], 140)) . '</p></article>';
}

function blog_card(array $b): string
{
    $url = '/blog-detail.php?id=' . (int)$b['id'];
    return '<article class="card"><a href="' . h($url) . '"><img class="cover" src="' . h(upload_url($b['image'])) . '" alt="" loading="lazy"></a>'
        . '<p class="meta">' . h(fmt_date($b['date'])) . ($b['author'] ? ' · ' . h($b['author']) : '') . '</p>'
        . '<h3><a href="' . h($url) . '">' . h($b['title']) . '</a></h3>'
        . '<p class="muted">' . h($b['excerpt'] !== '' ? $b['excerpt'] : snippet((string)$b['body'], 140)) . '</p></article>';
}

function exec_card(array $e): string
{
    $initial = mb_strtoupper(mb_substr(trim((string)$e['name']), 0, 1));
    $img = $e['image'] !== '' && is_file(upload_dir() . '/' . basename((string)$e['image']))
        ? '<img src="' . h(upload_url($e['image'])) . '" alt="' . h($e['name']) . '" loading="lazy">'
        : '<div class="avatar" aria-hidden="true">' . h($initial) . '</div>';
    return '<article class="exec">' . $img . '<h3>' . h($e['name']) . '</h3><p>' . h($e['position']) . '</p></article>';
}
