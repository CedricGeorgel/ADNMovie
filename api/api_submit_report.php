<?php
/**
 * API_SUBMIT_REPORT.PHP
 * Permet à tout utilisateur connecté de soumettre un bug report ou une feature request.
 * L'item est inséré en statut 'pending' dans system_management.
 * Les admins reçoivent une notification interne.
 */

require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/notifications_logic.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expirée.']);
    exit;
}

$userId = $_SESSION['user_id'];
$type   = $_POST['type']        ?? '';
$title  = trim($_POST['title']  ?? '');
$desc   = trim($_POST['description'] ?? '');

if (!in_array($type, ['bug', 'feature'], true) || empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
    exit;
}

if (mb_strlen($title) > 200) {
    echo json_encode(['success' => false, 'message' => 'Titre trop long (200 caractères max).']);
    exit;
}

try {
    db_execute(
        "INSERT INTO system_management (type, title, description, status, priority, submitted_by, created_at)
         VALUES (?, ?, ?, 'pending', 'medium', ?, NOW())",
        [$type, $title, $desc, $userId]
    );

    $reportId = (int)db_last_id();

    // Récupérer le username pour la notification
    $user = db_fetch_one('SELECT username FROM users WHERE id = ?', [$userId]);
    $username = $user['username'] ?? 'Utilisateur';

    try {
        notify_admins_report($username, $type, $title, $reportId);
    } catch (Exception $e) {
        // Notification non critique : on ne bloque pas la réponse
        error_log('notify_admins_report failed: ' . $e->getMessage());
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('api_submit_report error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
