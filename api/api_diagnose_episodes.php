<?php
/**
 * API_DIAGNOSE_EPISODES.PHP
 * Diagnostic dry-run de la tâche 10 (épisodes) — admin uniquement.
 *
 * Paramètres GET :
 *   date=YYYY-MM-DD  → simule sur une date précise (fenêtre date-1 → date)
 *   (aucun)          → fenêtre J-1 → J
 */
require_once 'auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';
require_once __DIR__ . '/../functions/series_logic.php';

header('Content-Type: text/plain; charset=utf-8');

if (!has_role('admin')) {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

$date = preg_replace('/[^0-9\-]/', '', $_GET['date'] ?? '');
$from = $date ? date('Y-m-d', strtotime($date . ' -1 day')) : date('Y-m-d', strtotime('-1 day'));
$to   = $date ?: date('Y-m-d');

echo "=== DIAGNOSTIC ÉPISODES (DRY-RUN) ===\n";
echo "Aucune notification ne sera envoyée.\n\n";

diagnose_episodes($from, $to);
