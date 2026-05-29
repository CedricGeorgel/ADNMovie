<?php
/**
 * API_COMMUNAUTE.PHP
 * GET ?mode=active            → top spécimens actifs
 * GET ?mode=search&q=pseudo   → recherche par pseudo
 */
require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';

$currentUserId = $_SESSION['user_id'] ?? null;
$mode          = $_GET['mode'] ?? 'active';

// ── Requête de base commune ────────────────────────────────────────────────────
$baseSelect = "
    SELECT
        u.id,
        u.username,
        u.avatar,
        u.role,
        u.backdrop_poster AS backdrop,
        COALESCE(r.cnt,  0)                     AS rating_count,
        COALESCE(mc.cnt, 0)                     AS comment_count,
        COALESCE(uc.cnt, 0)                     AS content_count,
        COALESCE(fr.cnt, 0)                     AS friend_count,
        (COALESCE(r.cnt, 0)
         + COALESCE(mc.cnt, 0) * 2
         + COALESCE(uc.cnt, 0) * 3)             AS activity_score
    FROM users u
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS cnt FROM ratings GROUP BY user_id
    ) r  ON r.user_id  = u.id
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS cnt FROM movie_comments GROUP BY user_id
    ) mc ON mc.user_id = u.id
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS cnt FROM user_content WHERE is_public = 1 GROUP BY user_id
    ) uc ON uc.user_id = u.id
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS cnt FROM (
            SELECT requester_id AS user_id FROM friendships WHERE status = 'accepted'
            UNION ALL
            SELECT addressee_id AS user_id FROM friendships WHERE status = 'accepted'
        ) f GROUP BY user_id
    ) fr ON fr.user_id = u.id
    WHERE u.is_anonymized = 0
      AND u.id != 'IA-ORACLE-001'
";

// ── Mode actif ────────────────────────────────────────────────────────────────
if ($mode === 'active') {
    $members = db_fetch_all(
        $baseSelect . "
        HAVING activity_score > 0
        ORDER BY activity_score DESC
        LIMIT 6",
        []
    ) ?: [];

    echo json_encode(['success' => true, 'members' => enrich_with_status($members, $currentUserId)]);
    exit;
}

// ── Mode recherche ────────────────────────────────────────────────────────────
if ($mode === 'search') {
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 2) {
        echo json_encode(['success' => true, 'members' => []]);
        exit;
    }

    $members = db_fetch_all(
        $baseSelect . " AND u.username LIKE ? ORDER BY u.username ASC LIMIT 20",
        ['%' . $q . '%']
    ) ?: [];

    echo json_encode(['success' => true, 'members' => enrich_with_status($members, $currentUserId)]);
    exit;
}

echo json_encode(['success' => false]);

// ── Helper : ajoute le statut d'amitié ────────────────────────────────────────
function enrich_with_status(array $members, ?string $currentUserId): array {
    if (!$currentUserId || empty($members)) return $members;

    $ids = array_column($members, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $params = array_merge([$currentUserId, $currentUserId], $ids);
    $friendships = db_fetch_all(
        "SELECT requester_id, addressee_id, status
         FROM friendships
         WHERE (requester_id = ? OR addressee_id = ?)
           AND (requester_id IN ($placeholders) OR addressee_id IN ($placeholders))",
        array_merge([$currentUserId, $currentUserId], $ids, $ids)
    ) ?: [];

    // Indexe par l'id de l'autre utilisateur
    $statusMap = [];
    foreach ($friendships as $f) {
        $other = $f['requester_id'] === $currentUserId ? $f['addressee_id'] : $f['requester_id'];
        if ($f['status'] === 'accepted') {
            $statusMap[$other] = 'accepted';
        } elseif ($f['requester_id'] === $currentUserId) {
            $statusMap[$other] = 'pending_sent';
        } else {
            $statusMap[$other] = 'pending_received';
        }
    }

    foreach ($members as &$m) {
        if ($m['id'] === $currentUserId) {
            $m['friend_status'] = 'self';
        } else {
            $m['friend_status'] = $statusMap[$m['id']] ?? 'none';
        }
    }
    unset($m);

    return $members;
}
