<?php
/**
 * API_FLAG_USER.PHP
 * Signale ou désignale un utilisateur (modérateur+).
 * POST : target_id, flagged (0|1)
 */
require_once 'auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

header('Content-Type: application/json');

if (!has_role('moderator')) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
    exit;
}

$targetId  = $_POST['target_id'] ?? '';
$flagValue = (int)($_POST['flagged'] ?? 0);

if (!$targetId) {
    echo json_encode(['success' => false, 'message' => 'ID manquant.']);
    exit;
}

if ($targetId === $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Impossible de se signaler soi-même.']);
    exit;
}

$target = db_fetch_one('SELECT id FROM users WHERE id = ?', [$targetId]);
if (!$target) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable.']);
    exit;
}

db_execute('UPDATE users SET is_flagged = ? WHERE id = ?', [$flagValue ? 1 : 0, $targetId]);

echo json_encode(['success' => true, 'flagged' => (bool)$flagValue]);
