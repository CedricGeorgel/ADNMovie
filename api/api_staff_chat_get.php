<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';
require_once __DIR__ . '/../functions/formatting.php';

if (!has_role('moderator')) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

$lastId = (int)($_GET['last_id'] ?? 0);

try {
    $rows = db_fetch_all(
        "SELECT m.id, m.user_id, m.message, m.created_at, u.username, u.avatar, u.role
         FROM room_messages m
         JOIN users u ON m.user_id = u.id
         WHERE m.room_id = 'STAFF-MOD' AND m.id > ?
         ORDER BY m.id ASC",
        [$lastId]
    );

    $messages = [];
    foreach ($rows as $row) {
        $messages[] = [
            'id'       => $row['id'],
            'user_id'  => $row['user_id'],
            'username' => $row['username'],
            'avatar'   => $row['avatar'] ?? 'assets/default-avatar.png',
            'role'     => $row['role'] ?? 'user',
            'text'     => chat_decrypt($row['message']),
            'time'     => date('H:i', strtotime($row['created_at'])),
        ];
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'messages' => []]);
}
