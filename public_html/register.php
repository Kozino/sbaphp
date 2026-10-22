<?php
require __DIR__ . '/../private/bootstrap.php';

$errors = [];
$done = null;
$v = [];
if (is_post()) {
    require_csrf();
    $v = array_map(fn($x) => trim((string)$x), [
        'first_name' => $_POST['first_name'] ?? '',
        'middle_name' => $_POST['middle_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'hometown' => $_POST['hometown'] ?? '',
        'hometown_parish' => $_POST['hometown_parish'] ?? '',
        'residential_address' => $_POST['residential_address'] ?? '',
        'permanent_address' => $_POST['permanent_address'] ?? '',
    ]);
    if ($v['first_name'] === '' || $v['last_name'] === '') $errors[] = 'First name and last name are required.';
    if (!valid_email($v['email'])) $errors[] = 'Please enter a valid email address.';
    if (!valid_phone($v['phone'])) $errors[] = 'Please enter a valid phone number.';
    if ($v['hometown'] === '') $errors[] = 'Please enter your hometown.';
    if (!$errors && val('SELECT 1 FROM members WHERE email = ?', [$v['email']])) {
        $errors[] = 'An account with this email already exists.';
    }
    if (!$errors) {
        try {
            $uid = new_member_id();
            q('INSERT INTO members (unique_id, first_name, middle_name, last_name, email, phone, hometown, hometown_parish, residential_address, permanent_address, status)
               VALUES (?,?,?,?,?,?,?,?,?,?,\'pending\')',
                [$uid, $v['first_name'], $v['middle_name'], $v['last_name'], $v['email'], $v['phone'], $v['hometown'],
                 $v['hometown_parish'], $v['residential_address'], $v['permanent_address']]);
            $done = ['id' => $uid, 'name' => trim($v['first_name'] . ' ' . $v['middle_name'] . ' ' . $v['last_name'])];
            $v = [];
        } catch (Throwable $e) {
            error_log('register: ' . $e->getMessage());
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
$val = fn($k) => h($v[$k] ?? '');

page_start('Register', 'register', 'Register as a member of the Savio Bosco Alphas 2012 association.');
page_head_block('Member Registration', 'Register your details. Once the executives approve your registration, your member ID unlocks the Audit Portal.', 'Join');
?>
<section class="section"><div class="wrap narrow">
<?php if ($done): ?>
  <div class="card">
    <div class="alert alert-success">Thank you, <?= h($done['name']) ?>. Your registration has been received and is awaiting approval by the executives.</div>
    <p>Your member ID is:</p>
    <div class="member-card"><span class="id"><?= h($done['id']) ?></span><span>Keep this ID safe. You will use it to sign in to the Audit Portal after approval.</span></div>
    <p class="muted">Note: the Audit Portal will not accept your ID until your registration is approved.</p>
    <p><a class="btn" href="/audit.php">Go to the Audit Portal</a></p>
  </div>
<?php else: ?>
  <?php foreach ($errors as $err) echo alert_html($err); ?>
  <form class="form card" method="post" action="/register.php" novalidate>
    <?= csrf_field() ?>
    <div class="form-row">
      <label>First name *<input name="first_name" required value="<?= $val('first_name') ?>" autocomplete="given-name"></label>
      <label>Middle name<input name="middle_name" value="<?= $val('middle_name') ?>" autocomplete="additional-name"></label>
      <label>Last name *<input name="last_name" required value="<?= $val('last_name') ?>" autocomplete="family-name"></label>
    </div>
    <div class="form-row">
      <label>Email *<input type="email" name="email" required value="<?= $val('email') ?>" autocomplete="email"></label>
      <label>Phone <small>(e.g. +234 803 000 0000)</small><input type="tel" name="phone" value="<?= $val('phone') ?>" autocomplete="tel"></label>
    </div>
    <div class="form-row">
      <label>Hometown *<input name="hometown" required value="<?= $val('hometown') ?>"></label>
      <label>Hometown parish<input name="hometown_parish" value="<?= $val('hometown_parish') ?>"></label>
    </div>
    <label>Residential address<textarea name="residential_address" rows="2"><?= $val('residential_address') ?></textarea></label>
    <label>Permanent address<textarea name="permanent_address" rows="2"><?= $val('permanent_address') ?></textarea></label>
    <div><button class="btn" type="submit">Submit registration</button></div>
  </form>
<?php endif; ?>
</div></section>
<?php page_end();
