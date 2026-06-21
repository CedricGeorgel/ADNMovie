<?php
/**
 * API/API_COMMENTS.PHP
 * Retourne les commentaires d'un film en JSON (arbre côté client).
 */

require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../components/comment.php';

start_persistent_session();

ini_set('display_errors', 0);
header('Content-Type: application/json');

$movieId = trim($_GET['movie_id'] ?? '');
if ($movieId === '') { echo json_encode(['success' => false, 'comments' => []]); exit; }

$isAdmin    = has_role('moderator');
$isLoggedIn = isset($_SESSION['user_id']);

try {
    $rows = db_fetch_all(
        "SELECT c.id, c.parent_id, c.user_id, c.content, c.created_at, c.is_censored,
                u.username, u.avatar, u.role
         FROM movie_comments c
         JOIN users u ON c.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci
         WHERE c.movie_id = ?
         ORDER BY c.created_at ASC",
        [$movieId]
    );

    $comments = [];
    foreach ($rows as $row) {
        $shield = '';
        if ($row['role'] === 'admin' || $row['role'] === 'superadmin') {
            $shield = '<span title="Administrateur" style="color:#f87171;font-size:0.65em;margin-left:3px;vertical-align:middle;">🛡</span>';
        } elseif ($row['role'] === 'moderator') {
            $shield = '<span title="Modérateur" style="color:#6ee7b7;font-size:0.65em;margin-left:3px;vertical-align:middle;">🛡</span>';
        }

        $comments[] = [
            'id'           => (int)$row['id'],
            'parent_id'    => $row['parent_id'] ? (int)$row['parent_id'] : null,
            'user_id'      => $row['user_id'],
            'username'     => $row['username'] ?? 'Anonyme',
            'avatar'       => $row['avatar']   ?? 'assets/default-avatar.png',
            'role_badge'   => $shield,
            'content_html' => nl2br(formatCommentContent($row['content'])),
            'created_at'   => $row['created_at'],
            'is_censored'  => (bool)$row['is_censored'],
            'is_admin'     => $isAdmin,
            'is_logged_in' => $isLoggedIn,
        ];
    }

    echo json_encode(['success' => true, 'comments' => $comments]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'comments' => [], 'error' => $isAdmin ? $e->getMessage() : '']);
}
