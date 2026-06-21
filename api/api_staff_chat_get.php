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
        "SELECT m.id, m.user_id, m.message, m.created_at, m.reply_to_id,
                u.username, u.avatar, u.role,
                ru.username AS reply_username,
                rm.message  AS reply_message
         FROM room_messages m
         JOIN users u ON m.user_id = u.id
         LEFT JOIN room_messages rm ON rm.id = m.reply_to_id
         LEFT JOIN users ru ON ru.id = rm.user_id
         WHERE m.room_id = 'STAFF-MOD' AND m.id > ?
         ORDER BY m.id ASC",
        [$lastId]
    );

    $messages = [];
    foreach ($rows as $row) {
        $replyPreview = null;
        if ($row['reply_to_id'] && $row['reply_message']) {
            $replyText = chat_decrypt($row['reply_message']);
            $replyPreview = [
                'id'       => (int)$row['reply_to_id'],
                'username' => $row['reply_username'] ?? '?',
                'snippet'  => mb_substr(strip_tags($replyText), 0, 80),
            ];
        }
        $messages[] = [
            'id'           => $row['id'],
            'user_id'      => $row['user_id'],
            'username'     => $row['username'],
            'avatar'       => $row['avatar'] ?? 'assets/default-avatar.png',
            'role'         => $row['role'] ?? 'user',
            'text'         => chat_decrypt($row['message']),
            'time'         => date('H:i', strtotime($row['created_at'])),
            'reply_to_id'  => $row['reply_to_id'] ? (int)$row['reply_to_id'] : null,
            'reply_preview'=> $replyPreview,
        ];
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'messages' => []]);
}
