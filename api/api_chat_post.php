<?php
require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/moderation.php';

$userId    = $_SESSION['user_id'] ?? null;
$roomId    = $_POST['room_id']    ?? null;
$text      = trim($_POST['message']     ?? '');
$replyToId = (int)($_POST['reply_to_id'] ?? 0) ?: null;

if (!$userId || !$roomId || empty($text)) exit;

$text = censor_content($text)['content'];

// Auto-migration : ajoute reply_to_id si la colonne n'existe pas encore
static $colChecked = false;
if (!$colChecked) {
    $colChecked = true;
    try {
        $exists = db_fetch_one(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'room_messages' AND COLUMN_NAME = 'reply_to_id'"
        );
        if (!$exists) {
            getPDO()->exec("ALTER TABLE room_messages ADD COLUMN reply_to_id INT NULL DEFAULT NULL");
        }
    } catch (\Throwable $e) {}
}

// Valide que le message cité appartient à la même room
if ($replyToId) {
    $ref = db_fetch_one('SELECT id FROM room_messages WHERE id = ? AND room_id = ?', [$replyToId, $roomId]);
    if (!$ref) $replyToId = null;
}

db_execute(
    "INSERT INTO room_messages (room_id, user_id, message, reply_to_id, created_at) VALUES (?, ?, ?, ?, NOW())",
    [$roomId, $userId, chat_encrypt($text), $replyToId]
);
db_execute(
    'UPDATE rooms SET last_activity_at = NOW() WHERE id = ?',
    [$roomId]
);
// Notifications (non bloquantes)
try {
    require_once __DIR__ . '/../functions/notifications_logic.php';

    $alreadyNotified = [];

    $sender     = db_fetch_one('SELECT username FROM users WHERE id = ?', [$userId]);
    $senderName = $sender['username'] ?? '';

    // 1. @modération — ping mods/admins même hors session
    if (has_moderation_mention($text)) {
        notify_moderation_mention($senderName, $roomId, "session.php?id={$roomId}");
    }

    // 2. Mentions @pseudo — priorité sur la notif "message"
    if (preg_match_all('/@(\w+)/', $text, $matches)) {
        foreach (array_unique($matches[1]) as $pseudo) {
            $notifiedId = notify_mention($pseudo, $userId, $senderName, $roomId);
            if ($notifiedId) $alreadyNotified[] = $notifiedId;
        }
    }

    // 3. Notif "message non lu" pour les membres non encore notifiés
    notify_room_message($roomId, $userId, $alreadyNotified);

} catch (Exception $e) {
    error_log('chat notifications failed: ' . $e->getMessage());
}

echo json_encode(['success' => true]);