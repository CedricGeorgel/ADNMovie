<?php
/**
 * API_RESTORE_SESSION.PHP
 * Restaure la session depuis un token stocké en localStorage (fallback iOS PWA).
 * POST _pwa_token=<raw token>
 */
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../api/auth_helpers.php';
require_once __DIR__ . '/../functions/core_db.php';

start_persistent_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

// Session déjà active, rien à faire
if (isset($_SESSION['user_id'])) {
    echo json_encode(['success' => true]);
    exit;
}

$token = trim($_POST['_pwa_token'] ?? '');
if (strlen($token) < 10) {
    echo json_encode(['success' => false]);
    exit;
}

$row = db_fetch_one(
    'SELECT user_id, expires_at FROM remember_tokens WHERE token_hash = ? AND expires_at > NOW()',
    [hash('sha256', $token)]
);

if (!$row) {
    echo json_encode(['success' => false]);
    exit;
}

$_SESSION['user_id'] = $row['user_id'];

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie(session_name(), session_id(), [
    'expires'  => time() + SESSION_LIFETIME,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Repose aussi le cookie remember_token si expiration < 15 jours
$remaining = strtotime($row['expires_at']) - time();
if ($remaining < 60 * 60 * 24 * 15) {
    set_remember_token($row['user_id']);
}

echo json_encode(['success' => true]);
