<?php
/**
 * API_DELETE_MOVIE.PHP
 * Supprime un film et toutes ses dépendances (admin uniquement).
 */
require_once 'auth_helpers.php'; 
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

header('Content-Type: application/json');

if (!has_role('admin')) {
    exit(json_encode(['success' => false, 'message' => 'Accès refusé']));
}

$movieId = (int)($_POST['movie_id'] ?? 0);

if (!$movieId) {
    exit(json_encode(['success' => false, 'message' => 'ID manquant']));
}

// Remplace : scan de movies_details/rates/*.json + unlink détail + update index JSON
// Les FK ON DELETE CASCADE suppriment automatiquement ratings et movie_dna
$rows = db_execute('DELETE FROM movies WHERE tmdb_id = ?', [$movieId]);

if ($rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Film introuvable en BDD.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => "Le film $movieId et ses dépendances ont été purgés."
]);