<?php
/**
 * API_ADMIN_DELETE_CONTENT.PHP
 * DELETE un film ou une série de la BDD (et toutes ses données liées).
 * POST { type: "movie"|"tv", tmdb_id: int }
 * Réservé superadmin.
 */
require_once __DIR__ . '/auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/auth.php';

header('Content-Type: application/json');
require_role('superadmin');

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$type   = $body['type']    ?? '';
$tmdbId = (int)($body['tmdb_id'] ?? 0);

if (!in_array($type, ['movie', 'tv'], true) || $tmdbId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'type et tmdb_id requis']);
    exit;
}

if ($type === 'movie') {
    // Les FK CASCADE couvrent : ratings, movie_dna, room_proposals, room_votes, room_events
    $deleted = db_execute('DELETE FROM movies WHERE tmdb_id = ?', [$tmdbId]);
    // user_wishlist n'a pas de FK → suppression manuelle
    db_execute('DELETE FROM user_wishlist WHERE movie_id = ?', [$tmdbId]);

    if (!$deleted) {
        echo json_encode(['ok' => false, 'error' => 'Film introuvable (tmdb_id ' . $tmdbId . ')']);
        exit;
    }

    echo json_encode(['ok' => true, 'deleted' => 'movie', 'tmdb_id' => $tmdbId]);

} else {
    // Résoudre l'id local de la série
    $seriesRow = db_fetch_one('SELECT id, title FROM series WHERE tmdb_id = ?', [$tmdbId]);
    if (!$seriesRow) {
        echo json_encode(['ok' => false, 'error' => 'Série introuvable (tmdb_id ' . $tmdbId . ')']);
        exit;
    }
    $seriesId    = (int)$seriesRow['id'];
    $seriesTitle = $seriesRow['title'];

    // Suppression manuelle (pas de FK CASCADE sur series)
    db_execute('DELETE FROM series_ratings  WHERE series_id = ?', [$seriesId]);
    db_execute('DELETE FROM season_ratings  WHERE series_id = ?', [$seriesId]);
    db_execute('DELETE FROM series_dna      WHERE series_id = ?', [$seriesId]);
    db_execute('DELETE FROM user_wishlist   WHERE series_id = ?', [$seriesId]);
    db_execute('DELETE FROM series_episodes WHERE series_id = ?', [$seriesId]);
    db_execute('DELETE FROM series          WHERE id        = ?', [$seriesId]);

    echo json_encode(['ok' => true, 'deleted' => 'tv', 'tmdb_id' => $tmdbId, 'title' => $seriesTitle]);
}
