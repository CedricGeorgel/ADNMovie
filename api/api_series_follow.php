<?php
/**
 * API_SERIES_FOLLOW.PHP
 * Gère le suivi des séries par les utilisateurs.
 *
 * POST action=follow&series_id=X  → suit une série
 * POST action=unfollow&series_id=X → ne suit plus une série
 * POST action=mark_ended&series_id=X → marque une série comme terminée
 * GET action=is_followed&series_id=X → vérifie si l'utilisateur suit une série
 * GET action=is_ended&series_id=X   → vérifie si l'utilisateur a marqué une série comme terminée
 */
require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/series_logic.php'; // Assurez-vous que ce fichier existe et contient les fonctions nécessaires

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$seriesId = isset($_POST['series_id']) ? (int)$_POST['series_id'] : (isset($_GET['series_id']) ? (int)$_GET['series_id'] : 0);

if (!$action || !$seriesId) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit;
}

switch ($action) {
    case 'follow':
        $result = follow_series($userId, $seriesId);
        echo json_encode(['success' => $result, 'followed' => $result]);
        break;

    case 'unfollow':
        $result = unfollow_series($userId, $seriesId);
        echo json_encode(['success' => $result, 'followed' => !$result]);
        break;

    case 'mark_ended':
        $result = mark_series_as_ended($userId, $seriesId);
        echo json_encode(['success' => $result, 'ended' => $result]);
        break;

    case 'is_followed':
        $isFollowed = is_series_followed_by_user($userId, $seriesId);
        echo json_encode(['success' => true, 'followed' => $isFollowed]);
        break;

    case 'is_ended':
        $isEnded = is_series_marked_as_ended($userId, $seriesId);
        echo json_encode(['success' => true, 'ended' => $isEnded]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
        break;
}
