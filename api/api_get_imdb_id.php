<?php
/**
 * API_GET_IMDB_ID.PHP
 * Récupère l'ID IMDb d'un film via TMDB (admin uniquement, à la volée).
 */
require_once 'auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

header('Content-Type: application/json');

if (!has_role('admin')) {
    exit(json_encode(['success' => false, 'message' => 'Accès refusé']));
}

$tmdbId = (int)($_GET['tmdb_id'] ?? 0);
if (!$tmdbId) {
    exit(json_encode(['success' => false, 'message' => 'ID manquant']));
}

$url = "https://api.themoviedb.org/3/movie/{$tmdbId}/external_ids?api_key=" . TMDB_API_KEY;
$ch  = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_CAINFO         => '/root/.ccr/ca-bundle.crt',
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$raw || $code !== 200) {
    exit(json_encode(['success' => false, 'message' => 'Erreur TMDB (' . $code . ')']));
}

$data   = json_decode($raw, true);
$imdbId = $data['imdb_id'] ?? null;

if (!$imdbId) {
    exit(json_encode(['success' => false, 'message' => 'ID IMDb introuvable pour ce film']));
}

echo json_encode(['success' => true, 'imdb_id' => $imdbId]);
