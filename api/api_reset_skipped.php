<?php
require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId = $_SESSION['user_id'];
$type   = $_POST['type'] ?? ''; // shoot_movies | shoot_series | algo_movies | algo_series

$skipKey = 'shoot_skip_' . $userId;

switch ($type) {
    case 'shoot_movies':
        $count = count($_SESSION[$skipKey]['movies'] ?? []);
        $_SESSION[$skipKey]['movies'] = [];
        echo json_encode(['success' => true, 'reset' => $count]);
        break;

    case 'shoot_series':
        $count = count($_SESSION[$skipKey]['series'] ?? []);
        $_SESSION[$skipKey]['series'] = [];
        echo json_encode(['success' => true, 'reset' => $count]);
        break;

    case 'algo_movies':
        // recommendation_feedback : movie_id > 0 = films
        $count = (int)(db_fetch_one(
            "SELECT COUNT(*) AS n FROM recommendation_feedback WHERE user_id = ? AND movie_id > 0 AND outcome = 'skipped'",
            [$userId]
        )['n'] ?? 0);
        db_execute(
            "UPDATE recommendation_feedback SET outcome = 'pending', outcome_at = NULL, is_active = 1
             WHERE user_id = ? AND movie_id > 0 AND outcome = 'skipped'",
            [$userId]
        );
        echo json_encode(['success' => true, 'reset' => $count]);
        break;

    case 'algo_series':
        // recommendation_feedback : movie_id < 0 = séries
        $count = (int)(db_fetch_one(
            "SELECT COUNT(*) AS n FROM recommendation_feedback WHERE user_id = ? AND movie_id < 0 AND outcome = 'skipped'",
            [$userId]
        )['n'] ?? 0);
        db_execute(
            "UPDATE recommendation_feedback SET outcome = 'pending', outcome_at = NULL, is_active = 1
             WHERE user_id = ? AND movie_id < 0 AND outcome = 'skipped'",
            [$userId]
        );
        echo json_encode(['success' => true, 'reset' => $count]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Type invalide']);
}
