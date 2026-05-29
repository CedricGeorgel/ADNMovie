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
    // Récupère les messages entre les deux utilisateurs, > last_id, ordre ASC
    $rows = db_fetch_all(
        "SELECT id, sender_id, body, sent_at
         FROM direct_messages
         WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
           AND id > ?
         ORDER BY id ASC",
        [$userId, $withId, $withId, $userId, $lastId]
    );

    $messages = [];
    foreach ($rows as $row) {
        $text = chat_decrypt($row['body']);
        $messages[] = [
            'id'        => (int)$row['id'],
            'sender_id' => $row['sender_id'],
            'text'      => $text,
            'time'      => date('H:i', strtotime($row['sent_at'])),
            'is_mine'   => ($row['sender_id'] === $userId),
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
