<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

if (!has_role('moderator')) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$text   = trim($_POST['message'] ?? '');

if (!$userId || empty($text)) {
    echo json_encode(['success' => false]);
    exit;
}

$text = mb_substr($text, 0, 1000);

try {
    db_execute(
        "INSERT INTO room_messages (room_id, user_id, message, created_at) VALUES ('STAFF-MOD', ?, ?, NOW())",
        [$userId, chat_encrypt($text)]
    );
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
