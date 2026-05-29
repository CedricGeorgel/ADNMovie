<?php
/**
 * API_UPDATE_PLATFORMS.PHP
 * Sauvegarde les IDs de plateformes sélectionnées par l'utilisateur.
 * POST body JSON : { "platform_ids": [8, 337, 119] }
 */
require_once 'auth_helpers.php';
start_persistent_session();
ini_set('display_errors', 0);
require_once __DIR__ . '/../functions/core_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$platformIds = $body['platform_ids'] ?? [];

if (!is_array($platformIds)) {
    echo json_encode(['success' => false, 'message' => 'Format invalide']);
    exit;
}

$platformIds = array_values(array_unique(array_map('intval', $platformIds)));

db_execute(
    'UPDATE users SET user_platforms = ? WHERE id = ?',
    [json_encode($platformIds), $_SESSION['user_id']]
);

echo json_encode(['success' => true]);
