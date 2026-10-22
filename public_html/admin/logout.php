<?php
require __DIR__ . '/../../private/bootstrap.php';
unset($_SESSION['admin_id']);
redirect('/admin/login.php');
