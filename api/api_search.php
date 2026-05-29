<?php
/**
 * API_SEARCH.PHP - Moteur de recherche hybride (Local BDD + TMDB)
 */
require_once 'auth_helpers.php'; 
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

if (empty($q)) {
    echo json_encode([]);
    exit;
}

// search_movies_hybrid() est dans utils.php — on vérifie qu'elle utilise
// bien la BDD. Si elle lisait movies_list.json, elle devra être mise à jour.
// Pour l'instant on l'appelle directement, elle sera migrée avec utils.php.
$results = search_movies_hybrid($q);

echo json_encode($results);
exit;