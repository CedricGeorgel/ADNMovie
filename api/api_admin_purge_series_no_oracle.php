<?php
/**
 * API_ADMIN_PURGE_SERIES_NO_ORACLE.PHP
 * Supprime toutes les séries sans note Oracle pour qu'elles soient
 * re-traitées au prochain affichage de leur fiche.
 * Réservé superadmin. POST sans body.
 */
require_once __DIR__ . '/auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/auth.php';

header('Content-Type: application/json');
require_role('admin');

// Séries sans rating Oracle
$series = db_fetch_all(
    "SELECT s.id, s.tmdb_id, s.title
     FROM series s
     WHERE NOT EXISTS (
         SELECT 1 FROM series_ratings sr
         WHERE sr.series_id = s.id AND sr.user_id = 'IA-ORACLE-001'
     )
     ORDER BY s.title"
);

if (empty($series)) {
    echo json_encode(['ok' => true, 'deleted' => 0, 'message' => 'Toutes les séries ont déjà une note Oracle.']);
    exit;
}

$deleted = 0;
$titles  = [];

foreach ($series as $s) {
    $sid = (int)$s['id'];
    db_execute('DELETE FROM series_ratings  WHERE series_id = ?', [$sid]);
    db_execute('DELETE FROM season_ratings  WHERE series_id = ?', [$sid]);
    db_execute('DELETE FROM series_dna      WHERE series_id = ?', [$sid]);
    db_execute('DELETE FROM user_wishlist   WHERE series_id = ?', [$sid]);
    db_execute('DELETE FROM series_episodes WHERE series_id = ?', [$sid]);
    db_execute('DELETE FROM series          WHERE id        = ?', [$sid]);
    $titles[] = $s['title'];
    $deleted++;
}

echo json_encode([
    'ok'      => true,
    'deleted' => $deleted,
    'titles'  => $titles,
]);
