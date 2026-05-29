<?php
/**
 * API/API_SEARCH_USERS.PHP
 * Recherche d'utilisateurs pour l'autocomplete @
 * GET ?q=<query>&context=fiche|archive|session&ref_id=<refId>&room_id=<roomId>
 *
 * Priorité selon le contexte :
 *   session  → uniquement les membres de la room
 *   fiche    → commentateurs du film, puis amis, puis autres
 *   archive  → commentateurs de l'archive, puis amis, puis autres
 */
require_once 'auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
header('Content-Type: application/json');

$q       = trim($_GET['q'] ?? '');
$context = $_GET['context'] ?? 'fiche';
$myId    = $_SESSION['user_id'] ?? null;

if (mb_strlen($q) < 1) { echo '[]'; exit; }

$like = $q . '%';

// ── Contexte session : uniquement les membres présents ────────────────────────
if ($context === 'session') {
    $roomId = trim($_GET['room_id'] ?? '');
    if (!$roomId) { echo '[]'; exit; }

    $rows = db_fetch_all(
        "SELECT u.id, u.username, u.avatar
         FROM room_members rm
         JOIN users u ON u.id = rm.user_id
         WHERE rm.room_id = ? AND rm.is_banned = 0 AND u.username LIKE ?
           AND u.id != ?
         ORDER BY u.username
         LIMIT 7",
        [$roomId, $like, $myId ?? '']
    ) ?: [];

    echo json_encode(array_map(fn($r) => [
        'username' => $r['username'],
        'avatar'   => $r['avatar'],
    ], $rows));
    exit;
}

// ── Contexte fiche / archive : commentateurs > amis > autres ─────────────────
$refId = trim($_GET['ref_id'] ?? '');

$commenters = [];
if ($refId) {
    if ($context === 'archive') {
        // content_comments utilise content_id (int)
        $cRows = db_fetch_all(
            "SELECT DISTINCT u.id, u.username, u.avatar
             FROM content_comments cc
             JOIN users u ON u.id = cc.user_id
             WHERE cc.content_id = ? AND u.username LIKE ?
             ORDER BY u.username LIMIT 7",
            [(int)$refId, $like]
        ) ?: [];
    } else {
        $cRows = db_fetch_all(
            "SELECT DISTINCT u.id, u.username, u.avatar
             FROM movie_comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.movie_id = ? AND u.username LIKE ?
             ORDER BY u.username LIMIT 7",
            [$refId, $like]
        ) ?: [];
    }
    foreach ($cRows as $r) $commenters[$r['id']] = $r;
}

$friends = [];
if ($myId) {
    $fRows = db_fetch_all(
        "SELECT u.id, u.username, u.avatar
         FROM friendships f
         JOIN users u ON u.id = IF(f.requester_id = ?, f.addressee_id, f.requester_id)
         WHERE (f.requester_id = ? OR f.addressee_id = ?) AND f.status = 'accepted'
           AND u.username LIKE ?
         ORDER BY u.username LIMIT 7",
        [$myId, $myId, $myId, $like]
    ) ?: [];
    foreach ($fRows as $r) {
        if (!isset($commenters[$r['id']])) $friends[$r['id']] = $r;
    }
}

$others = db_fetch_all(
    "SELECT id, username, avatar FROM users WHERE username LIKE ? ORDER BY username LIMIT 7",
    [$like]
) ?: [];

$seen    = [];
$results = [];
foreach (array_merge(array_values($commenters), array_values($friends)) as $r) {
    $seen[$r['id']] = true;
    $results[] = ['username' => $r['username'], 'avatar' => $r['avatar']];
}
foreach ($others as $r) {
    if (!isset($seen[$r['id']])) {
        $results[] = ['username' => $r['username'], 'avatar' => $r['avatar']];
    }
}

echo json_encode(array_slice($results, 0, 7));
