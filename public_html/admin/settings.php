<?php
require __DIR__ . '/_auth.php';

admin_action('/admin/settings.php', function () {
    foreach (['contact_email', 'contact_phone', 'contact_address'] as $k) {
        if (isset($_POST[$k])) {
            $v = trim((string)$_POST[$k]);
            if ($k === 'contact_email' && $v !== '' && !valid_email($v)) throw new RuntimeException('Please enter a valid contact email.');
            q('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$k, $v]);
        }
    }
    return 'Contact details saved.';
});
$s = settings();

admin_start('Settings', 'settings', $ADMIN);
echo admin_head_flash();
?>
<div class="panel" style="max-width:720px">
  <h2>Contact details</h2>
  <p class="muted" style="margin-top:0">Shown in the website footer, the Contact page and the chatbot.</p>
  <form class="form" method="post" action="/admin/settings.php">
    <?= csrf_field() ?>
    <label>Contact email<input type="email" name="contact_email" value="<?= h($s['contact_email']) ?>"></label>
    <label>Phone<input name="contact_phone" value="<?= h($s['contact_phone']) ?>"></label>
    <label>Address<textarea name="contact_address" rows="2"><?= h($s['contact_address']) ?></textarea></label>
    <div><button class="btn" type="submit">Save</button></div>
  </form>
</div>
<?php admin_end();
