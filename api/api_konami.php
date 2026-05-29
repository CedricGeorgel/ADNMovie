<?php
/**
 * API_KONAMI.PHP — Déclenché par le Konami Code
 * Attribue le badge ANOMALY_X3 si pas encore obtenu.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/auth_helpers.php';
start_persistent_session();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['badge' => false]);
    exit;
}

$userId = $_SESSION['user_id'];

$already = db_fetch_one(
    'SELECT 1 FROM user_badges WHERE user_id = ? AND badge_id = ?',
    [$userId, 'KONAMI']
);

$awarded = false;
if (!$already) {
    db_execute(
        'INSERT IGNORE INTO user_badges (user_id, badge_id, earned_at) VALUES (?, ?, NOW())',
        [$userId, 'KONAMI']
    );
    $awarded = true;
}

echo json_encode(['badge' => $awarded]);
