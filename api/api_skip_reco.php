<?php
/**
 * API_SKIP_RECO.PHP
 * Marque une recommandation comme "skippée" (Pas intéressé).
 * Déclenche un recalcul des recos au prochain chargement.
 */
require_once 'auth_helpers.php'; 
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId  = $_SESSION['user_id'];
$movieId = (int)($_POST['movie_id'] ?? 0);

if ($movieId === 0) {
    echo json_encode(['success' => false, 'message' => 'movie_id manquant']);
    exit;
}

$rows = db_execute(
    "UPDATE recommendation_feedback
     SET outcome = 'skipped', outcome_at = NOW(), is_active = 0
     WHERE user_id = ? AND movie_id = ? AND outcome = 'pending'",
    [$userId, $movieId]
);

echo json_encode(['success' => true, 'skipped' => $rows > 0]);