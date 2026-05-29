<?php
/**
 * API_SIGNAL.PHP
 * Signale un contenu (commentaire, liste/critique, film, série) aux modérateurs.
 *
 * POST :
 * entity_type  'comment' | 'content' | 'movie' | 'tv' | 'season' | 'episode'
 * entity_id    int
 * reason       'inappropriate' | 'spam' | 'wrong_info' | 'other'
 */
require_once 'auth_helpers.php';
start_persistent_session();
ini_set('display_errors', 0);
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/notifications_logic.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']); exit;
}

$userId     = $_SESSION['user_id'];
$entityType = $_POST['entity_type'] ?? '';
$entityId   = (int)($_POST['entity_id'] ?? 0);
$reason     = $_POST['reason'] ?? 'other';

// L'ajout de 'tv', 'season', et 'episode' débloque la situation
if (!in_array($entityType, ['comment', 'content', 'movie', 'tv', 'season', 'episode'], true) || !$entityId) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']); exit;
}

if (!in_array($reason, ['inappropriate', 'spam', 'wrong_info', 'other'], true)) {
    $reason = 'other';
}

$reasonLabels = [
    'inappropriate' => 'Contenu inapproprié / offensant',
    'spam'          => 'Spam ou hors-sujet',
    'wrong_info'    => 'Informations incorrectes',
    'other'         => 'Autre',
];

$user = db_fetch_one('SELECT username FROM users WHERE id = ?', [$userId]);
$username = $user['username'] ?? 'Opérateur';

$title = "[Signal] " . ($reasonLabels[$reason] ?? 'Autre') . " — {$entityType} #{$entityId}";
$desc  = "Signalé par {$username} (user_id: {$userId})\nType: {$entityType}\nID: {$entityId}\nRaison: " . ($reasonLabels[$reason] ?? $reason);

try {
    db_execute(
        "INSERT INTO system_management (type, title, description, status, priority, user_id, created_at)
         VALUES ('signal', ?, ?, 'pending', 'high', ?, NOW())",
        [$title, $desc, $userId]
    );
    $reportId = (int)db_last_id();

    notify_mods_signal($username, $entityType, $reportId);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('api_signal error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}