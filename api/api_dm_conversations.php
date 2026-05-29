<?php
/**
 * API/API_DM_CONVERSATIONS.PHP
 * GET → retourne les conversations DM de l'utilisateur connecté.
 */
require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/friends_logic.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { echo json_encode(['ok' => false]); exit; }

$convs = get_dm_conversations($userId);
echo json_encode(['ok' => true, 'conversations' => $convs]);
