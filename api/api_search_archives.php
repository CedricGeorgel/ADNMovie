<?php
/**
 * API/API_SEARCH_ARCHIVES.PHP
 * Recherche d'archives publiques par titre pour l'autocomplete !##
 * GET ?q=<query>
 * Retourne JSON : [{slug, title}]
 */
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo '[]'; exit; }

$rows = db_fetch_all(
    "SELECT slug, title FROM user_content WHERE is_public = 1 AND title LIKE ? ORDER BY title LIMIT 7",
    ['%' . $q . '%']
) ?: [];

echo json_encode($rows);
