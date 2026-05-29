<?php
// api/api_recommend.php
require_once 'auth_helpers.php'; 
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/recommendation_logic.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId = $_SESSION['user_id'];
$recos = RECO_LIMIT ? getRecommendations($userId, RECO_LIMIT) : [];
 // sécurisé : entre 1 et 50

$results = getRecommendations($userId, RECO_LIMIT);

echo json_encode([
    'success'         => true,
    'recommendations' => $results,
]);