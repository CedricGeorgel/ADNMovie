<?php
/**
 * API_UPDATE_PRIVACY.PHP
 * Met à jour les préférences de confidentialité (colonne privacy_settings JSON).
 * POST : adn_public (0|1), collection_public (0|1), wishlist_public (0|1)
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

$userId = $_SESSION['user_id'];

$row = db_fetch_one('SELECT privacy_settings FROM users WHERE id = ?', [$userId]);
$current = json_decode($row['privacy_settings'] ?? '{}', true) ?: [];

// Fusionne uniquement les clés envoyées (les autres restent inchangées)
if (isset($_POST['adn_public']))        $current['adn_public']        = (bool)(int)$_POST['adn_public'];
if (isset($_POST['collection_public'])) $current['collection_public'] = (bool)(int)$_POST['collection_public'];
if (isset($_POST['wishlist_public']))   $current['wishlist_public']   = (bool)(int)$_POST['wishlist_public'];
if (isset($_POST['content_public']))    $current['content_public']    = (bool)(int)$_POST['content_public'];

db_execute(
    'UPDATE users SET privacy_settings = ? WHERE id = ?',
    [json_encode($current, JSON_UNESCAPED_UNICODE), $userId]
);

echo json_encode(['success' => true]);
