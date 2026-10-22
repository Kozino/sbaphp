<?php
require __DIR__ . '/_auth.php';

admin_action('/admin/password.php', function () use ($ADMIN) {
    $current = (string)($_POST['current'] ?? '');
    $next = (string)($_POST['next'] ?? '');
    if ($current === '') throw new RuntimeException('Please enter your current password.');
    if (strlen($next) < 8) throw new RuntimeException('The new password must be at least 8 characters long.');
    $row = one('SELECT password FROM admins WHERE id = ?', [$ADMIN['id']]);
    if (!$row || !password_verify($current, $row['password'])) throw new RuntimeException('The current password is incorrect.');
    q('UPDATE admins SET password = ? WHERE id = ?', [password_hash($next, PASSWORD_BCRYPT), $ADMIN['id']]);
    return 'Password changed.';
});

admin_start('Change password', 'password', $ADMIN);
echo admin_head_flash();
?>
<div class="panel" style="max-width:480px">
  <form class="form" method="post" action="/admin/password.php">
    <?= csrf_field() ?>
    <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
    <label>New password <small>(at least 8 characters)</small><input type="password" name="next" required minlength="8" autocomplete="new-password"></label>
    <div><button class="btn" type="submit">Change password</button></div>
  </form>
</div>
<?php admin_end();
