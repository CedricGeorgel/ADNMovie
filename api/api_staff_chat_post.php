<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

if (!has_role('moderator')) {
    echo json_encode(['success' => false]);
    exit;
}

$userId    = $_SESSION['user_id'] ?? null;
$text      = trim($_POST['message'] ?? '');
$replyToId = (int)($_POST['reply_to_id'] ?? 0) ?: null;

if (!$userId || empty($text)) {
    echo json_encode(['success' => false]);
    exit;
}

$text = mb_substr($text, 0, 1000);

if ($replyToId) {
    $ref = db_fetch_one("SELECT id FROM room_messages WHERE id = ? AND room_id = 'STAFF-MOD'", [$replyToId]);
    if (!$ref) $replyToId = null;
}

try {
    db_execute(
        "INSERT INTO room_messages (room_id, user_id, message, reply_to_id, created_at) VALUES ('STAFF-MOD', ?, ?, ?, NOW())",
        [$userId, chat_encrypt($text), $replyToId]
    );
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
