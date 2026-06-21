<?php
/**
 * API_CONTENT_COMMENTS.PHP
 * GET  ?content_id=X&offset=0  → liste des commentaires
 * POST action=post  content_id, body, parent_id?
 * POST action=delete comment_id  (auteur ou admin)
 */
require_once 'auth_helpers.php';
start_persistent_session();
ini_set('display_errors', 0);
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/moderation.php';

header('Content-Type: application/json');

function resolve_refs(string $text): string {
    $text = preg_replace_callback('/(^|[\s])\/\/tv-(\d+)/', function($m) {
        $row = db_fetch_one('SELECT title, poster, year FROM series WHERE tmdb_id = ?', [(int)$m[2]]);
        if (!$row) return $m[1] . '//tv-' . $m[2];
        return $m[1] . '//[tv-' . $m[2] . '|' . rawurlencode($row['title']) . '|' . rawurlencode($row['poster'] ?? '') . '|' . ($row['year'] ?? '') . ']';
    }, $text);
    $text = preg_replace_callback('/(^|[\s])\/\/(\d+)/', function($m) {
        $row = db_fetch_one('SELECT title, poster, year FROM movies WHERE tmdb_id = ?', [(int)$m[2]]);
        if (!$row) return $m[1] . '//' . $m[2];
        return $m[1] . '//[' . $m[2] . '|' . rawurlencode($row['title']) . '|' . rawurlencode($row['poster'] ?? '') . '|' . ($row['year'] ?? '') . ']';
    }, $text);
    $text = preg_replace_callback('/(^|[\s])\/tv-(\d+)/', function($m) {
        $row = db_fetch_one('SELECT title FROM series WHERE tmdb_id = ?', [(int)$m[2]]);
        return $m[1] . ($row ? '/[tv-' . $m[2] . ':' . $row['title'] . ']' : '/tv-' . $m[2]);
    }, $text);
    $text = preg_replace_callback('/(^|[\s])\/(\d+)/', function($m) {
        $row = db_fetch_one('SELECT title FROM movies WHERE tmdb_id = ?', [(int)$m[2]]);
        return $m[1] . ($row ? '/[' . $m[2] . ':' . $row['title'] . ']' : '/' . $m[2]);
    }, $text);
    return $text;
}

$userId = $_SESSION['user_id'] ?? null;

// ── GET ───────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $contentId = (int)($_GET['content_id'] ?? 0);
    if (!$contentId) { echo json_encode(['success' => false, 'comments' => []]); exit; }

    $isAdmin    = has_role('moderator');
    $isLoggedIn = isset($userId);

    $rows = db_fetch_all(
        'SELECT cc.id, cc.content_id, cc.user_id, cc.parent_id, cc.body, cc.created_at, cc.is_censored,
                u.username, u.avatar, u.role
         FROM content_comments cc
         JOIN users u ON u.id = cc.user_id
         WHERE cc.content_id = ?
         ORDER BY cc.created_at ASC',
        [$contentId]
    ) ?: [];

    require_once __DIR__ . '/../components/comment.php';
    $comments = [];
    foreach ($rows as $r) {
        $shield = '';
        if (in_array($r['role'], ['admin','superadmin'])) $shield = '<span title="Administrateur" style="color:#f87171;font-size:0.65em;margin-left:3px;vertical-align:middle;">🛡</span>';
        elseif ($r['role'] === 'moderator')               $shield = '<span title="Modérateur" style="color:#6ee7b7;font-size:0.65em;margin-left:3px;vertical-align:middle;">🛡</span>';

        $resolved = resolve_refs($r['body']);
        $comments[] = [
            'id'           => (int)$r['id'],
            'parent_id'    => $r['parent_id'] ? (int)$r['parent_id'] : null,
            'user_id'      => $r['user_id'],
            'username'     => $r['username'] ?? 'Anonyme',
            'avatar'       => $r['avatar']   ?? 'assets/default-avatar.png',
            'role_badge'   => $shield,
            'content_html' => nl2br(formatCommentContent($resolved)),
            'created_at'   => $r['created_at'],
            'is_censored'  => (bool)($r['is_censored'] ?? false),
            'is_admin'     => $isAdmin,
            'is_logged_in' => $isLoggedIn,
        ];
    }
    echo json_encode(['success' => true, 'comments' => $comments]);
    exit;
}

// ── POST ──────────────────────────────────────────────────────────────────────
if (!$userId) { echo json_encode(['success' => false, 'message' => 'Non authentifié']); exit; }

$action = $_POST['action'] ?? '';

if ($action === 'post') {
    $contentId = (int)($_POST['content_id'] ?? 0);
    $body      = trim($_POST['body'] ?? '');
    $parentId  = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (!$contentId || $body === '') {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']); exit;
    }

    $content = db_fetch_one('SELECT id, is_public FROM user_content WHERE id = ?', [$contentId]);
    if (!$content) { echo json_encode(['success' => false, 'message' => 'Contenu introuvable']); exit; }

    $body = censor_content($body)['content'];

    db_execute(
        'INSERT INTO content_comments (content_id, user_id, parent_id, body) VALUES (?,?,?,?)',
        [$contentId, $userId, $parentId, $body]
    );
    $newId = db_last_id();

    $row = db_fetch_one(
        'SELECT cc.id, cc.content_id, cc.user_id, cc.parent_id, cc.body, cc.created_at,
                u.username, u.avatar, u.role
         FROM content_comments cc JOIN users u ON u.id = cc.user_id
         WHERE cc.id = ?',
        [$newId]
    );
    $row['body'] = resolve_refs($row['body']);
    echo json_encode(['success' => true, 'comment' => $row]);
    exit;
}

if ($action === 'delete') {
    $commentId = (int)($_POST['comment_id'] ?? 0);
    if (!$commentId) { echo json_encode(['success' => false]); exit; }

    $com = db_fetch_one('SELECT id, user_id FROM content_comments WHERE id = ?', [$commentId]);
    if (!$com) { echo json_encode(['success' => false, 'message' => 'Introuvable']); exit; }

    $currentUser = db_fetch_one('SELECT role FROM users WHERE id = ?', [$userId]);
    $isAdmin     = in_array($currentUser['role'] ?? '', ['admin', 'superadmin', 'moderator']);

    if ($com['user_id'] !== $userId && !$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']); exit;
    }

    db_execute('DELETE FROM content_comments WHERE id = ?', [$commentId]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action inconnue']);
