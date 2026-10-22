<?php
require __DIR__ . '/../../private/bootstrap.php';
require_once __DIR__ . '/../../private/chat.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_post()) {
    http_response_code(405);
    exit(json_encode(['error' => 'Use POST.']));
}
$question = trim((string)($_POST['message'] ?? ''));
if ($question === '') {
    http_response_code(400);
    exit(json_encode(['error' => 'Please enter a question.']));
}
if (mb_strlen($question) > 500) {
    http_response_code(400);
    exit(json_encode(['error' => 'Please keep your question under 500 characters.']));
}
// Simple per-session limit: 15 questions per 10 minutes
$now = time();
if (!isset($_SESSION['chat']) || $now > ($_SESSION['chat']['reset'] ?? 0)) {
    $_SESSION['chat'] = ['count' => 0, 'reset' => $now + 600];
}
$_SESSION['chat']['count']++;
if ($_SESSION['chat']['count'] > 15) {
    http_response_code(429);
    exit(json_encode(['error' => 'Too many questions — please try again in a few minutes.']));
}
try {
    echo json_encode(['answer' => chat_answer($question)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('chat: ' . $e->getMessage());
    http_response_code(502);
    echo json_encode(['error' => 'The assistant is temporarily unavailable. Please try again shortly.']);
}
