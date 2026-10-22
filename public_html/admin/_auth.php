<?php
require __DIR__ . '/../../private/bootstrap.php';
$ADMIN = require_admin();

/** Runs POST actions; $handler returns a flash message string. Always PRG. */
function admin_action(string $redirectTo, callable $handler): void
{
    if (!is_post()) return;
    require_csrf();
    try {
        $msg = $handler();
        flash('success', is_string($msg) ? $msg : 'Saved.');
    } catch (Throwable $e) {
        if (!$e instanceof RuntimeException && !$e instanceof InvalidArgumentException) error_log('admin: ' . $e->getMessage());
        flash('error', $e instanceof RuntimeException || $e instanceof InvalidArgumentException
            ? $e->getMessage() : 'Something went wrong. Please try again.');
    }
    redirect($redirectTo);
}

function admin_head_flash(): string
{
    return flash_html();
}
