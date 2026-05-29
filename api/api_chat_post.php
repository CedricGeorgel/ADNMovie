<?php
require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/moderation.php';

$userId = $_SESSION['user_id'] ?? null;
$roomId = $_POST['room_id'] ?? null;
$text   = trim($_POST['message'] ?? '');

if (!$userId || !$roomId || empty($text)) exit;

$text = censor_content($text)['content'];

db_execute(
    "INSERT INTO room_messages (room_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())",
    [$roomId, $userId, chat_encrypt($text)]
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