<?php
/**
 * API_CONTENT_DELETE.PHP
 * POST id — supprime un contenu et ses items/commentaires associés
 */
require_once 'auth_helpers.php';
start_persistent_session();
ini_set('display_errors', 0);
require_once __DIR__ . '/../functions/core_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']); exit;
}
$userId    = $_SESSION['user_id'];
$contentId = (int)($_POST['id'] ?? 0);

if (!$contentId) { echo json_encode(['success' => false]); exit; }

$row = db_fetch_one('SELECT id, user_id FROM user_content WHERE id = ?', [$contentId]);
if (!$row) { echo json_encode(['success' => false, 'message' => 'Introuvable']); exit; }

$currentUser = db_fetch_one('SELECT role FROM users WHERE id = ?', [$userId]);
$isAdmin     = in_array($currentUser['role'] ?? '', ['admin', 'superadmin']);

if ($row['user_id'] !== $userId && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']); exit;
}

db_execute('DELETE FROM user_content_items  WHERE content_id = ?', [$contentId]);
db_execute('DELETE FROM content_comments    WHERE content_id = ?', [$contentId]);
db_execute('DELETE FROM user_content        WHERE id = ?',         [$contentId]);

echo json_encode(['success' => true]);
