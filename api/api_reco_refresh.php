<?php
/**
 * API_RECO_REFRESH.PHP
 * POST type=film|series
 * Vérifie : ADN changé + cooldown 7 jours
 * Action   : invalide les recos pending, log le refresh
 */
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../api/auth_helpers.php';
require_once __DIR__ . '/../functions/core_db.php';

start_persistent_session();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode invalide']); exit;
}

$userId = $_SESSION['user_id'];
$type   = $_POST['type'] ?? '';
if (!in_array($type, ['film', 'series'])) {
    echo json_encode(['success' => false, 'message' => 'Type invalide']); exit;
}

// ── Crée la table si besoin ───────────────────────────────────────────────────
try {
    getPDO()->exec("
        CREATE TABLE IF NOT EXISTS reco_refresh_log (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id      VARCHAR(64)           NOT NULL,
            type         ENUM('film','series') NOT NULL,
            refreshed_at TIMESTAMP             NOT NULL DEFAULT CURRENT_TIMESTAMP,
            adn_hash     VARCHAR(32)           NOT NULL,
            INDEX idx_user_type (user_id, type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {}

// ── Hash ADN actuel ───────────────────────────────────────────────────────────
$table      = ($type === 'series') ? 'user_series_dna' : 'user_dna';
$adnRows    = db_fetch_all(
    "SELECT criterio, avg_score FROM {$table} WHERE user_id = ? ORDER BY criterio",
    [$userId]
);
$currentHash = md5(json_encode($adnRows));

// ── Dernier refresh ───────────────────────────────────────────────────────────
$last = db_fetch_one(
    "SELECT refreshed_at, adn_hash FROM reco_refresh_log
     WHERE user_id = ? AND type = ? ORDER BY refreshed_at DESC LIMIT 1",
    [$userId, $type]
);

$cooldownOk = !$last || (strtotime($last['refreshed_at']) + 7 * 24 * 3600) <= time();
$adnChanged = !$last || $last['adn_hash'] !== $currentHash;

if (!$cooldownOk) {
    $daysLeft = (int)ceil((strtotime($last['refreshed_at']) + 7 * 24 * 3600 - time()) / 86400);
    echo json_encode(['success' => false, 'message' => "Cooldown actif — disponible dans {$daysLeft}j"]);
    exit;
}
if (!$adnChanged) {
    echo json_encode(['success' => false, 'message' => 'ADN inchangé depuis le dernier refresh']);
    exit;
}

// ── Invalide les recos pending ────────────────────────────────────────────────
$movieIdFilter = ($type === 'film') ? 'movie_id > 0' : 'movie_id < 0';
db_execute(
    "UPDATE recommendation_feedback SET is_active = 0
     WHERE user_id = ? AND {$movieIdFilter} AND outcome = 'pending'",
    [$userId]
);

// ── Log le refresh ────────────────────────────────────────────────────────────
db_execute(
    "INSERT INTO reco_refresh_log (user_id, type, adn_hash) VALUES (?, ?, ?)",
    [$userId, $type, $currentHash]
);

echo json_encode(['success' => true]);
