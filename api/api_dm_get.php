<?php
/**
 * API_DM_GET.PHP
 * Récupère les messages directs entre l'utilisateur courant et un ami.
 *
 * GET ?with=userId&last_id=0
 * Returns : {success, messages: [{id, sender_id, text, time, is_mine}]}
 */

ini_set('display_errors', 0);
require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'messages' => [], 'error' => 'Non authentifié']);
    exit;
}

$withId = trim($_GET['with'] ?? '');
$lastId = (int)($_GET['last_id'] ?? 0);

if (!$withId || $withId === $userId) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

try {
    // Auto-migration : ajoute reply_to_id si absent
    static $colChecked = false;
    if (!$colChecked) {
        $colChecked = true;
        $exists = db_fetch_one("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='direct_messages' AND COLUMN_NAME='reply_to_id'");
        if (!$exists) getPDO()->exec("ALTER TABLE direct_messages ADD COLUMN reply_to_id INT NULL DEFAULT NULL");
    }

    $rows = db_fetch_all(
        "SELECT m.id, m.sender_id, m.body, m.sent_at, m.reply_to_id,
                u.username AS sender_username, u.avatar AS sender_avatar, u.role AS sender_role,
                rm.body AS reply_body,
                ru.username AS reply_username
         FROM direct_messages m
         JOIN users u ON u.id = m.sender_id
         LEFT JOIN direct_messages rm ON rm.id = m.reply_to_id
         LEFT JOIN users ru ON ru.id = rm.sender_id
         WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
           AND m.id > ?
         ORDER BY m.id ASC",
        [$userId, $withId, $withId, $userId, $lastId]
    );

    $messages = [];
    foreach ($rows as $row) {
        $text = chat_decrypt($row['body']);
        $replyPreview = null;
        if ($row['reply_to_id'] && $row['reply_body']) {
            $replyText = chat_decrypt($row['reply_body']);
            $replyPreview = [
                'id'       => (int)$row['reply_to_id'],
                'username' => $row['reply_username'] ?? '?',
                'snippet'  => mb_substr(strip_tags($replyText), 0, 80),
            ];
        }
        $messages[] = [
            'id'           => (int)$row['id'],
            'user_id'      => $row['sender_id'],
            'username'     => $row['sender_username'] ?? '?',
            'avatar'       => $row['sender_avatar'] ?? 'assets/default-avatar.png',
            'role'         => $row['sender_role'] ?? 'user',
            'text'         => $text,
            'time'         => date('H:i', strtotime($row['sent_at'])),
            'is_mine'      => ($row['sender_id'] === $userId),
            'reply_to_id'  => $row['reply_to_id'] ? (int)$row['reply_to_id'] : null,
            'reply_preview'=> $replyPreview,
        ];
    }

    // Marque les messages reçus comme lus
    db_execute(
        "UPDATE direct_messages SET is_read = 1
         WHERE receiver_id = ? AND sender_id = ? AND is_read = 0",
        [$userId, $withId]
    );

    echo json_encode(['success' => true, 'messages' => $messages]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'messages' => [], 'error' => $e->getMessage()]);
}
