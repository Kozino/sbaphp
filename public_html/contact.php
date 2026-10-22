<?php
require __DIR__ . '/../private/bootstrap.php';

$errors = [];
$sent = false;
$v = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];
if (is_post()) {
    require_csrf();
    foreach (array_keys($v) as $k) $v[$k] = trim((string)($_POST[$k] ?? ''));
    if ($v['name'] === '' || $v['email'] === '' || $v['subject'] === '' || $v['message'] === '') {
        $errors[] = 'Please fill in all required fields.';
    } elseif (!valid_email($v['email'])) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        q('INSERT INTO messages (name, email, phone, subject, message) VALUES (?,?,?,?,?)',
            [$v['name'], $v['email'], $v['phone'], $v['subject'], $v['message']]);
        $sent = true;
        $v = array_map(fn() => '', $v);
    }
}
$s = settings();
page_start('Contact', 'contact', 'Get in touch with the executives of the Savio Bosco Alphas 2012 association.');
page_head_block('Contact Us', 'Send a message to the executives. We reply by email or phone.', 'Get in touch');
?>
<section class="section"><div class="wrap grid-2">
  <div class="card">
    <h2>Reach the association</h2>
    <dl class="kv">
      <dt>Email</dt><dd><a href="mailto:<?= h($s['contact_email']) ?>"><?= h($s['contact_email']) ?></a></dd>
      <dt>Phone</dt><dd><?= h($s['contact_phone']) ?></dd>
      <dt>Address</dt><dd><?= h($s['contact_address']) ?></dd>
    </dl>
  </div>
  <div class="card">
    <h2>Send a message</h2>
    <?php if ($sent): ?><div class="alert alert-success">Thank you. Your message has been sent to the executives.</div><?php endif; ?>
    <?php foreach ($errors as $err) echo alert_html($err); ?>
    <form class="form" method="post" action="/contact.php" novalidate>
      <?= csrf_field() ?>
      <label>Your name *<input name="name" required value="<?= h($v['name']) ?>"></label>
      <div class="form-row">
        <label>Email *<input type="email" name="email" required value="<?= h($v['email']) ?>"></label>
        <label>Phone<input type="tel" name="phone" value="<?= h($v['phone']) ?>"></label>
      </div>
      <label>Subject *<input name="subject" required value="<?= h($v['subject']) ?>"></label>
      <label>Message *<textarea name="message" required rows="5"><?= h($v['message']) ?></textarea></label>
      <div><button class="btn" type="submit">Send message</button></div>
    </form>
  </div>
</div></section>
<?php page_end();
