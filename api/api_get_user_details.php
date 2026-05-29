<?php
/**
 * API_GET_USER_DETAILS.PHP
 * Retourne les détails d'un utilisateur (admin uniquement).
 */
require_once 'auth_helpers.php'; 
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

header('Content-Type: application/json');

if (!has_role('admin')) {
    exit(json_encode(['success' => false]));
}

$targetId = $_GET['id'] ?? '';
if (!$targetId) exit(json_encode(['success' => false]));

// Remplace : file_exists + json_decode("bdd/users/{id}.json")
$user = db_fetch_one('SELECT id, role, is_flagged FROM users WHERE id = ?', [$targetId]);

if (!$user) {
    exit(json_encode(['success' => false]));
}

$badges = db_fetch_all(
    'SELECT badge_id FROM user_badges WHERE user_id = ?',
    [$targetId]
);

$seenCount = (int)db_fetch_one(
    'SELECT COUNT(*) AS cnt FROM ratings WHERE user_id = ?',
    [$targetId]
)['cnt'];

$likedCount = (int)db_fetch_one(
    'SELECT COUNT(*) AS cnt FROM ratings WHERE user_id = ? AND is_liked = 1',
    [$targetId]
)['cnt'];

echo json_encode([
    'success'    => true,
    'badges'     => array_column($badges, 'badge_id'),
    'seen_count' => $seenCount,
    'liked_count'=> $likedCount,
    'role'       => $user['role']       ?? 'user',
    'is_flagged' => (bool)($user['is_flagged'] ?? false),
]);