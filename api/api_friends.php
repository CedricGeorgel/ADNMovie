<?php
/**
 * API_FRIENDS.PHP
 * Gestion des demandes d'amis.
 *
 * POST actions : send | accept | decline | remove
 * Returns : {ok: bool, status: string}
 */

require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/friends_logic.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['ok' => false, 'status' => 'none', 'error' => 'Non authentifié']);
    exit;
}

$action   = trim($_POST['action']   ?? '');
$targetId = trim($_POST['target_id'] ?? '');

if (!$action || !$targetId || $targetId === $userId) {
    echo json_encode(['ok' => false, 'status' => 'none', 'error' => 'Paramètres invalides']);
    exit;
}

// Vérifie que l'utilisateur cible existe
$targetUser = db_fetch_one('SELECT id, username FROM users WHERE id = ?', [$targetId]);
if (!$targetUser) {
    echo json_encode(['ok' => false, 'status' => 'none', 'error' => 'Utilisateur introuvable']);
    exit;
}

$ok = false;

switch ($action) {
    case 'send':
        $ok = send_friend_request($userId, $targetId);
        if ($ok) {
            try {
                require_once __DIR__ . '/../functions/notifications_logic.php';
                $sender = db_fetch_one('SELECT username FROM users WHERE id = ?', [$userId]);
                notify_friend_request($userId, $sender['username'] ?? '', $targetId);
            } catch (Exception $e) {
                error_log('notify_friend_request failed: ' . $e->getMessage());
            }
        }
        break;

    case 'accept':
        // $targetId est le requester, $userId est l'addressee
        $ok = accept_friend_request($targetId, $userId);
        if ($ok) {
            try {
                require_once __DIR__ . '/../functions/notifications_logic.php';
                $acceptor = db_fetch_one('SELECT username FROM users WHERE id = ?', [$userId]);
                notify_friend_accepted($userId, $acceptor['username'] ?? '', $targetId);
            } catch (Exception $e) {
                error_log('notify_friend_accepted failed: ' . $e->getMessage());
            }
        }
        break;

    case 'decline':
    case 'remove':
        decline_or_remove_friend($userId, $targetId);
        $ok = true;
        break;

    default:
        echo json_encode(['ok' => false, 'status' => 'none', 'error' => 'Action inconnue']);
        exit;
}

$newStatus = get_friendship_status($userId, $targetId);

echo json_encode(['ok' => $ok, 'status' => $newStatus]);
