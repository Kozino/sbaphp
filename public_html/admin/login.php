<?php
require __DIR__ . '/../../private/bootstrap.php';
if (admin_user()) redirect('/admin/index.php');
$error = null;
if (is_post()) {
    require_csrf();
    $u = trim((string)($_POST['username'] ?? ''));
    $p = (string)($_POST['password'] ?? '');
    $admin = $u !== '' ? one('SELECT * FROM admins WHERE username = ?', [$u]) : null;
    if ($admin && $p !== '' && password_verify($p, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        redirect('/admin/index.php');
    }
    $error = $u === '' || $p === '' ? 'Please enter your username and password.' : 'Invalid username or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Admin sign in · SBA 2012</title><link rel="stylesheet" href="/assets/css/site.css"></head>
<body class="admin"><div class="login-wrap"><div class="login-box">
  <div class="center" style="margin-bottom:10px"><img src="/assets/img/logo.png" alt="" width="64" height="64" style="margin:0 auto"></div>
  <h1 class="center" style="font-size:1.5rem">Admin sign in</h1>
  <?= alert_html($error) ?>
  <form class="form" method="post" action="/admin/login.php" novalidate>
    <?= csrf_field() ?>
    <label>Username<input name="username" required autocomplete="username" autofocus></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn" type="submit">Sign in</button>
  </form>
  <p class="muted center" style="margin-top:14px"><a href="/">← Back to the website</a></p>
</div></div></body></html>
