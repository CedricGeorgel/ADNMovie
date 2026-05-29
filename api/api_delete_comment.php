<?php
require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

if (!has_role('moderator')) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
    exit;
}

$commentId = (int)($_POST['comment_id'] ?? 0);

if ($commentId > 0) {
    db_execute("DELETE FROM movie_comments WHERE id = ?", [$commentId]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'ID invalide.']);
}