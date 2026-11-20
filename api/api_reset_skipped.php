<?php
require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId  = $_SESSION['user_id'];
$type    = $_POST['type'] ?? 'movies'; // 'movies' | 'series'
$skipKey = 'shoot_skip_' . $userId;

$skipped = $_SESSION[$skipKey][$type] ?? [];
$count   = count($skipped);
$_SESSION[$skipKey][$type] = [];

echo json_encode(['success' => true, 'reset' => $count, 'type' => $type]);
