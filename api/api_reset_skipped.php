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

$count = db_fetch_one(
    "SELECT COUNT(*) AS n FROM recommendation_feedback WHERE user_id = ? AND outcome = 'skipped'",
    [$userId]
)['n'] ?? 0;

db_execute(
    "DELETE FROM recommendation_feedback WHERE user_id = ? AND outcome = 'skipped'",
    [$userId]
);

echo json_encode(['success' => true, 'reset' => (int)$count]);
