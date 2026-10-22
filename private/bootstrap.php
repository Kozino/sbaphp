<?php
declare(strict_types=1);

const SCHEMA_VERSION = '1';
const UPLOAD_MAX_BYTES = 20 * 1024 * 1024;
const APP_NAME = 'Savio Bosco Alphas 2012';
const APP_SHORT = 'SBA 2012';
const APP_MOTTO = 'Ecce quam bonum et quam iucundum habitare fratres in unum';
const APP_MOTTO_REF = 'Psalm 133:1';
const APP_MOTTO_TRANSLATION = 'Behold, how good and pleasant it is when brothers dwell in unity';
const APP_TAGLINE = 'One seminary family, one brotherhood for life.';
const ID_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
const DEFAULT_CONTACT = [
    'contact_email' => 'info@savioboscoalphas.org',
    'contact_phone' => '+234 803 000 0000',
    'contact_address' => 'St. Dominic Savio Seminary, Akpu, Orumba South LGA, Anambra State, Nigeria',
];
const PAYMENT_TYPES = [
    'annual_due' => 'Annual Due',
    'financial_presence' => 'Financial Presence',
    'burial_contribution' => 'Burial Contribution',
    'wedding_contribution' => 'Wedding Contribution',
];
const DOC_TYPES = [
    'financial_report' => 'Financial Report',
    'minutes' => 'Minutes of Meeting',
    'attendance' => 'Attendance Record',
    'financial_presence' => 'Financial Presence',
    'constitution' => 'Constitution',
];
const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
const DOC_FILE_TYPES = [
    'application/pdf' => 'pdf',
    'text/plain' => 'txt',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];

date_default_timezone_set('Africa/Lagos');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

function cfg(): array
{
    static $c = null;
    return $c ??= require __DIR__ . '/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('sba_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/* ---------------- Database ---------------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $c = cfg();
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['db_host'], $c['db_port'], $c['db_name']),
        $c['db_user'],
        $c['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    ensure_schema($pdo);
    return $pdo;
}

/** Creates/updates tables when SCHEMA_VERSION changes and seeds the first admin. */
function ensure_schema(PDO $pdo): void
{
    $hasMeta = $pdo->query("SHOW TABLES LIKE 'app_meta'")->fetchColumn();
    $ver = $hasMeta
        ? $pdo->query("SELECT meta_value FROM app_meta WHERE meta_key = 'schema_version'")->fetchColumn()
        : false;
    if ($ver === SCHEMA_VERSION) return;

    $sql = (string)file_get_contents(__DIR__ . '/schema.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $pdo->exec($stmt);
    }
    $pdo->prepare(
        "INSERT INTO app_meta (meta_key, meta_value) VALUES ('schema_version', ?)
         ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)"
    )->execute([SCHEMA_VERSION]);

    if ((int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO admins (username, name, password) VALUES (?, ?, ?)')
            ->execute(['admin', 'Administrator', password_hash(cfg()['default_admin_password'], PASSWORD_BCRYPT)]);
    }
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row ?: null;
}

function val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function settings(): array
{
    static $s = null;
    if ($s === null) {
        $s = DEFAULT_CONTACT;
        foreach (all('SELECT `key`, `value` FROM settings') as $r) {
            $s[$r['key']] = $r['value'];
        }
    }
    return $s;
}

/* ---------------- HTTP / security helpers ---------------- */

function h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void
{
    $t = (string)($_POST['_csrf'] ?? '');
    if ($t === '' || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Your form session expired. Go back, refresh the page and try again.');
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_html(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $out .= '<div class="alert alert-' . h($f['type']) . '">' . h($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

function alert_html(?string $msg, string $type = 'error'): string
{
    return $msg ? '<div class="alert alert-' . h($type) . '">' . h($msg) . '</div>' : '';
}

function param(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $_GET[$key] ?? $default));
}

/* ---------------- Admin auth ---------------- */

function admin_user(): ?array
{
    if (empty($_SESSION['admin_id'])) return null;
    return one('SELECT id, username, name FROM admins WHERE id = ?', [(int)$_SESSION['admin_id']]);
}

function require_admin(): array
{
    $u = admin_user();
    if (!$u) redirect('/admin/login.php');
    return $u;
}

/* ---------------- Formatting & domain rules ---------------- */

function fmt_money($n): string
{
    $v = round((float)$n, 2);
    $s = number_format($v, 2, '.', ',');
    return '₦' . preg_replace('/\.00$/', '', $s);
}

function fmt_date(?string $d): string
{
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date('j F Y', $t) : $d;
}

function fmt_date_short(?string $d): string
{
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date('j M Y', $t) : $d;
}

/** Mirrors the React site: Paid / Partially Paid / Owing / Contribution / No Record */
function payment_status(float $expected, float $paid): array
{
    if ($expected > 0 && $paid >= $expected) return ['Paid', 'ok'];
    if ($expected > 0 && $paid > 0) return ['Partially Paid', 'warn'];
    if ($expected > 0) return ['Owing', 'bad'];
    if ($paid > 0) return ['Contribution', 'ok'];
    return ['No Record', 'muted'];
}

function full_name(array $m): string
{
    return trim(implode(' ', array_filter([$m['first_name'] ?? '', $m['middle_name'] ?? '', $m['last_name'] ?? ''])));
}

function valid_email(string $v): bool
{
    return (bool)preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $v);
}

function valid_phone(string $v): bool
{
    return $v === '' || (bool)preg_match('/^\+?[0-9\s\-()]{8,18}$/', $v);
}

function valid_year($v, int $min = 1900, int $max = 2200): ?int
{
    $n = filter_var($v, FILTER_VALIDATE_INT);
    return ($n !== false && $n >= $min && $n <= $max) ? (int)$n : null;
}

function new_member_id(): string
{
    for ($i = 0; $i < 20; $i++) {
        $id = 'SBA12';
        for ($j = 0; $j < 3; $j++) {
            $id .= ID_CHARS[random_int(0, strlen(ID_CHARS) - 1)];
        }
        if (!val('SELECT 1 FROM members WHERE unique_id = ?', [$id])) return $id;
    }
    throw new RuntimeException('Could not create a member ID. Please try again.');
}

/**
 * Rich text for news/blog bodies. Allows a small set of tags (the old React
 * editor's output), strips everything else, keeps only safe links. Plain text
 * is turned into paragraphs.
 */
function rich(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') return '';
    if (!preg_match('/<[a-z][\s\S]*>/i', $raw)) {
        $paras = preg_split("/\n\s*\n/", $raw) ?: [$raw];
        return implode('', array_map(fn($p) => '<p>' . nl2br(h(trim($p))) . '</p>', $paras));
    }
    $clean = strip_tags($raw, '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a>');
    $clean = preg_replace_callback('/<a\s+([^>]*)>/i', function ($m) {
        if (preg_match('/href\s*=\s*["\']([^"\']*)["\']/i', $m[1], $x) && preg_match('#^(https?://|/|mailto:)#i', $x[1])) {
            return '<a href="' . h($x[1]) . '" rel="noopener">';
        }
        return '<a>';
    }, $clean);
    return (string)preg_replace('/<(p|br|strong|b|em|i|u|ul|ol|li|h2|h3|h4|blockquote)\s+[^>]*>/i', '<$1>', $clean);
}

function snippet(string $html, int $len = 200): string
{
    $t = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len)) . '…' : $t;
}

/* ---------------- Uploads ---------------- */

/** Saves an uploaded file to $dir and returns its stored name, or null if none sent. */
function save_upload(string $field, string $prefix, string $dir, array $allowed): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The file could not be uploaded (code ' . $f['error'] . '). Files must be 20 MB or smaller.');
    }
    if ($f['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('The file is larger than 20 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = $allowed[$mime] ?? null;
    if (!$ext) {
        throw new RuntimeException('That file type is not allowed.');
    }
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('The file could not be saved on the server.');
    }
    return $name;
}

function delete_upload(?string $name, string $dir): void
{
    $name = basename((string)$name);
    if ($name !== '' && is_file($dir . '/' . $name)) @unlink($dir . '/' . $name);
}

function upload_dir(): string
{
    return cfg()['upload_dir'];
}

function document_dir(): string
{
    return cfg()['document_dir'];
}

/* Tiny helper for the report generators */
function current_year(): int
{
    return (int)date('Y');
}

require_once __DIR__ . '/layout.php';
