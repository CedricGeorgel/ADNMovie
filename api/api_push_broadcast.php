<?php
/**
 * API/API_PUSH_BROADCAST.PHP
 * POST { title, body, url, target }
 * target = "all" → tous les abonnés, sinon user_id spécifique.
 * Réservé admin+.
 */
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/push_logic.php';

header('Content-Type: application/json');
require_role('admin');

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$title  = trim($body['title'] ?? '');
$text   = trim($body['body']  ?? '');
$url    = trim($body['url']   ?? '/');
$target = trim($body['target'] ?? 'all');

if (!$title || !$text) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'title and body required']);
    exit;
}

if ($target === 'all') {
    $subs = db_fetch_all('SELECT endpoint, p256dh, auth FROM push_subscriptions', []);
} else {
    $subs = db_fetch_all(
        'SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?',
        [$target]
    );
}

$sent = 0;
foreach ($subs as $sub) {
    if (push_send_one($sub, $title, $text, $url)) $sent++;
}

echo json_encode(['ok' => true, 'sent' => $sent]);
