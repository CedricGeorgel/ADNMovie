<?php
/**
 * API_DM_POST.PHP
 * Envoie un message direct à un ami.
 *
 * POST {to: userId, message: text}
 * Returns : {success: bool}
 */

require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/friends_logic.php';
require_once __DIR__ . '/../functions/moderation.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$toId = trim($_POST['to'] ?? '');
$text = trim($_POST['message'] ?? '');

if (!$toId || empty($text) || $toId === $userId) {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    exit;
}

// Vérifie que les deux utilisateurs sont amis (statut accepté)
$status = get_friendship_status($userId, $toId);
if ($status !== 'accepted') {
    echo json_encode(['success' => false, 'error' => 'Vous devez être amis pour envoyer un message']);
    exit;
}

$text      = censor_content($text)['content'];
$encrypted = chat_encrypt($text);

db_execute(
    "INSERT INTO direct_messages (sender_id, receiver_id, body, is_read, sent_at)
     VALUES (?, ?, ?, 0, NOW())",
    [$userId, $toId, $encrypted]
);

try {
    require_once __DIR__ . '/../functions/push_logic.php';
    $sender = db_fetch_one('SELECT username FROM users WHERE id = ?', [$userId]);
    $name   = $sender['username'] ?? 'Quelqu\'un';
    send_push_to_user($toId, 'ADN Movie', "{$name} vous a envoyé un message", "chat.php?with={$userId}");
} catch (Throwable) {}

echo json_encode(['success' => true]);
