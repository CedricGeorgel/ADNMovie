<?php
/**
 * API/API_PUSH_SUBSCRIBE.PHP
 * POST  { endpoint, keys: { p256dh, auth } }  → sauvegarde la subscription.
 * DELETE { endpoint }                          → supprime la subscription.
 */
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/auth_helpers.php';

start_persistent_session();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthenticated']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'DELETE') {
    $endpoint = trim($body['endpoint'] ?? '');
    if (!$endpoint) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'missing endpoint']);
        exit;
    }
    db_execute('DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?', [$endpoint, $userId]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method not allowed']);
    exit;
}

$endpoint = trim($body['endpoint'] ?? '');
$p256dh   = trim($body['keys']['p256dh'] ?? '');
$auth     = trim($body['keys']['auth']   ?? '');

if (!$endpoint || !$p256dh || !$auth) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing fields']);
    exit;
}

if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !str_starts_with($endpoint, 'https://')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid endpoint']);
    exit;
}

db_execute(
    "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth)",
    [$userId, $endpoint, $p256dh, $auth]
);

echo json_encode(['ok' => true]);
