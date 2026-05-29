<?php
/**
 * API/API_COMMENTS.PHP
 * Retourne les commentaires d'un film en HTML (scroll infini).
 * Utilise renderCommentItem() depuis components/comment.php.
 */

require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../components/avatar.php';
require_once __DIR__ . '/../components/comment.php';

ini_set('display_errors', 0);

$movieId = trim($_GET['movie_id'] ?? '');
$offset  = (int)($_GET['offset']   ?? 0);
$limit   = 15;

if ($movieId === '') exit;

$isAdmin = has_role('moderator');

try {
    $comments = db_fetch_all(
        "SELECT c.*, u.username, u.avatar, u.role
         FROM movie_comments c
         JOIN users u ON c.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci
         WHERE c.movie_id = ?
         ORDER BY COALESCE(c.parent_id, c.id) DESC, c.created_at ASC
         LIMIT ? OFFSET ?",
        [$movieId, $limit, $offset]
    );

    if (empty($comments)) exit;

    foreach ($comments as $com) {
        renderCommentItem($com, $isAdmin, isset($_SESSION['user_id']));
    }

} catch (Exception $e) {
    if ($isAdmin) {
        echo '<small style="color:red;">Erreur : ' . h($e->getMessage()) . '</small>';
    }
}
