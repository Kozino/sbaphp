<?php
require __DIR__ . '/../private/bootstrap.php';

/*
 * Serves documents stored OUTSIDE the web folder. Allowed for: signed-in admins,
 * or an active member who has opened the Audit Portal in this browser session.
 */
$docId = (int)($_GET['doc'] ?? 0);
$allowed = admin_user() !== null;
if (!$allowed && !empty($_SESSION['audit_member_id'])) {
    $allowed = (bool)val("SELECT 1 FROM members WHERE id = ? AND status = 'active'", [(int)$_SESSION['audit_member_id']]);
}
if (!$allowed) {
    http_response_code(403);
    exit('Please sign in to the Member Audit Portal to open this document.');
}
$doc = one('SELECT * FROM documents WHERE id = ?', [$docId]);
if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

if ($doc['filename'] === '') {
    // Text-only document (e.g. the constitution text)
    page_start($doc['title'], '', '');
    echo '<section class="section"><div class="wrap narrow"><p><a href="/audit.php">← Back to the Audit Portal</a></p>';
    echo '<h1>' . h($doc['title']) . '</h1><p class="muted">' . h(DOC_TYPES[$doc['type']] ?? $doc['type']) . ($doc['year'] ? ' · ' . (int)$doc['year'] : '') . '</p>';
    echo '<pre style="white-space:pre-wrap;font-family:ui-monospace,Menlo,monospace;font-size:.92rem;background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px">' . h($doc['text']) . '</pre></div></section>';
    page_end();
    exit;
}

$name = basename((string)$doc['filename']);
$path = document_dir() . '/' . $name;
if (!is_file($path)) {
    http_response_code(404);
    exit('The file is no longer available.');
}
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$mime = ['pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8', 'doc' => 'application/msword',
         'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'][$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $doc['title']) . '.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
