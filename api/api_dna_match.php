<?php
/**
 * API_DNA_MATCH.PHP
 * POST action=reveal&match_user_id=X  → enregistre l'accord de l'utilisateur courant
 */
require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/push_logic.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId      = $_SESSION['user_id'];
$action      = $_POST['action'] ?? '';
$matchUserId = $_POST['match_user_id'] ?? '';
$dnaType     = $_POST['dna_type'] ?? 'movie'; // 'movie' | 'series'

$revealTable   = $dnaType === 'series' ? 'series_dna_match_reveals' : 'dna_match_reveals';
$notifRevealId = ($dnaType === 'series' ? 'series_reveal_' : 'dna_reveal_') . $userId;
$notifMutualId = ($dnaType === 'series' ? 'series_mutual_' : 'dna_mutual_') . $userId;

if ($action === 'reveal' && $matchUserId && $matchUserId !== $userId) {
    $isNew = db_execute(
        "INSERT IGNORE INTO $revealTable (user_id, match_user_id, revealed_at) VALUES (?, ?, NOW())",
        [$userId, $matchUserId]
    );

    if ($isNew) {
        db_execute(
            "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
             VALUES (?, 'mention', ?, 'GÉNOME_INCONNU souhaite synchroniser son ADN au vôtre', 'communaute.php', 0, NOW())
             ON DUPLICATE KEY UPDATE created_at = NOW(), is_read = 0",
            [$matchUserId, $notifRevealId]
        );
        send_push_to_user($matchUserId, 'ADN Movie', 'GÉNOME_INCONNU souhaite synchroniser son ADN au vôtre', 'communaute.php');
    }

    $theyRevealed = db_fetch_one(
        "SELECT 1 FROM $revealTable WHERE user_id = ? AND match_user_id = ?",
        [$matchUserId, $userId]
    );

    if ($theyRevealed) {
        $me   = db_fetch_one('SELECT username FROM users WHERE id = ?', [$userId]);
        $them = db_fetch_one('SELECT id, username, avatar FROM users WHERE id = ?', [$matchUserId]);
        $mutualTitle = ($me['username'] ?? 'Un spécimen') . ' a synchronisé son ADN au vôtre';
        db_execute(
            "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
             VALUES (?, 'mention', ?, ?, 'communaute.php', 0, NOW())
             ON DUPLICATE KEY UPDATE title = VALUES(title), created_at = NOW(), is_read = 0",
            [$matchUserId, $notifMutualId, $mutualTitle]
        );
        send_push_to_user($matchUserId, 'ADN Movie', $mutualTitle, 'communaute.php');

        echo json_encode([
            'success' => true,
            'mutual'  => true,
            'user'    => [
                'id'       => $them['id'] ?? '',
                'username' => $them['username'] ?? '',
                'avatar'   => $them['avatar'] ?? '',
            ],
        ]);
    } else {
        echo json_encode(['success' => true, 'mutual' => false]);
    }
    exit;
}

if ($action === 'cancel' && $matchUserId && $matchUserId !== $userId) {
    db_execute(
        "DELETE FROM $revealTable WHERE user_id = ? AND match_user_id = ?",
        [$userId, $matchUserId]
    );
    db_execute(
        "DELETE FROM notifications WHERE user_id = ? AND source_id = ?",
        [$matchUserId, $notifRevealId]
    );
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action invalide']);
