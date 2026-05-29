<?php
/**
 * API_RESET_YOUTUBE_CACHE.PHP
 * Remet youtube_id à NULL pour les films où la valeur est 'NONE',
 * afin de forcer un re-fetch au prochain chargement de la fiche.
 * Admin uniquement.
 */
require_once 'auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    $pdo = getPDO();

    // Count before
    $count = $pdo->query("SELECT COUNT(*) FROM movies WHERE youtube_id = 'NONE'")->fetchColumn();

    // Reset
    $pdo->exec("UPDATE movies SET youtube_id = NULL WHERE youtube_id = 'NONE'");

    echo json_encode([
        'success' => true,
        'reset'   => (int)$count,
        'message' => "{$count} film(s) remis en queue pour re-fetch YouTube.",
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
