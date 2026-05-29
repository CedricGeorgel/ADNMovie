<?php
/**
 * API_ADMIN.PHP
 * Gestion des badges par les administrateurs.
 */
require_once 'auth_helpers.php'; 
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

header('Content-Type: application/json');

if (!has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé. Protocole de sécurité activé.']);
    exit;
}

$action   = $_POST['action']   ?? '';
$targetId = $_POST['target_id'] ?? '';
$badgeKey = $_POST['badge_key'] ?? '';

if (empty($targetId) || empty($badgeKey)) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants.']);
    exit;
}

// Vérifie que l'utilisateur cible existe
// Remplace : file_exists("bdd/users/{id}.json")
$target = db_fetch_one('SELECT id FROM users WHERE id = ?', [$targetId]);
if (!$target) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable.']);
    exit;
}

if ($action === 'award_badge') {
    // Remplace : lecture + écriture avec flock sur users/{id}.json
    $already = db_fetch_one(
        'SELECT 1 FROM user_badges WHERE user_id = ? AND badge_id = ?',
        [$targetId, $badgeKey]
    );
    if ($already) {
        echo json_encode(['success' => false, 'message' => 'Badge déjà possédé.']);
        exit;
    }
    db_execute(
        'INSERT INTO user_badges (user_id, badge_id, earned_at) VALUES (?, ?, NOW())',
        [$targetId, $badgeKey]
    );
    echo json_encode(['success' => true, 'message' => 'Badge octroyé.']);

} elseif ($action === 'revoke_badge') {
    $rows = db_execute(
        'DELETE FROM user_badges WHERE user_id = ? AND badge_id = ?',
        [$targetId, $badgeKey]
    );
    if ($rows === 0) {
        echo json_encode(['success' => false, 'message' => "Cet utilisateur ne possède pas ce badge."]);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Badge révoqué.']);

} else {
    echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
}