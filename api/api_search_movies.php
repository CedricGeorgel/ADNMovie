<?php
/**
 * API/API_SEARCH_MOVIES.PHP
 * Recherche de films ET séries par titre pour l'autocomplete !#
 * GET ?q=<query>
 * Retourne JSON : [{id, title, type}]  type = 'movie' | 'tv'
 */
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo '[]'; exit; }

$rows = db_fetch_all(
    "(SELECT tmdb_id AS id, title, 'movie' AS type FROM movies  WHERE title LIKE ? ORDER BY title LIMIT 7)
     UNION ALL
     (SELECT tmdb_id AS id, title, 'tv'    AS type FROM series  WHERE title LIKE ? ORDER BY title LIMIT 7)
     ORDER BY title LIMIT 10",
    ['%' . $q . '%', '%' . $q . '%']
) ?: [];

echo json_encode($rows);
