<?php
/**
 * API_ADMIN_IMPORT_PROVIDER_MOVIES.PHP
 * Importe une page de films depuis TMDB Discover pour un provider donné (région FR).
 * Pour chaque film : upsert metadata + Oracle + providers_data.
 * Traite 20 films par appel (une page Discover). Appelé en boucle depuis le JS admin.
 * POST { provider_id: int, page: int }
 */
require_once __DIR__ . '/auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/api_tmdb.php';
require_once __DIR__ . '/../functions/auth.php';

header('Content-Type: application/json');
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$providerId = (int)($body['provider_id'] ?? 350);
$page       = max(1, (int)($body['page'] ?? 1));

if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY)) {
    echo json_encode(['ok' => false, 'error' => 'TMDB_API_KEY manquante']);
    exit;
}

// ── Discover : films du provider sur FR ──────────────────────────────────────
$discoverUrl = "https://api.themoviedb.org/3/discover/movie"
    . "?api_key=" . TMDB_API_KEY
    . "&with_watch_providers={$providerId}"
    . "&watch_region=FR"
    . "&sort_by=popularity.desc"
    . "&page={$page}";

$raw = @file_get_contents($discoverUrl);
if (!$raw) {
    echo json_encode(['ok' => false, 'error' => 'Échec appel TMDB Discover']);
    exit;
}

$discover    = json_decode($raw, true);
$totalPages  = (int)($discover['total_pages']   ?? 1);
$totalResult = (int)($discover['total_results'] ?? 0);
$results     = $discover['results']             ?? [];

if (empty($results)) {
    echo json_encode(['ok' => true, 'page' => $page, 'total_pages' => $totalPages, 'imported' => 0, 'movies' => []]);
    exit;
}

$imported = 0;
$log      = [];

foreach ($results as $item) {
    $tmdbId = (int)($item['id'] ?? 0);
    if (!$tmdbId) continue;

    // ── Upsert metadata + Oracle (réutilise get_or_create_movie) ─────────────
    $movieData = get_or_create_movie($tmdbId);
    if (!$movieData) {
        $log[] = "#{$tmdbId} — fetch échoué, ignoré";
        usleep(200_000);
        continue;
    }

    // ── Providers FR ─────────────────────────────────────────────────────────
    $provUrl = "https://api.themoviedb.org/3/movie/{$tmdbId}/watch/providers?api_key=" . TMDB_API_KEY;
    $provRaw = @file_get_contents($provUrl);
    if ($provRaw) {
        $provData  = json_decode($provRaw, true);
        $providers = $provData['results']['FR'] ?? [];
        db_execute(
            'UPDATE movies SET providers_data = ?, providers_updated_at = NOW() WHERE tmdb_id = ?',
            [json_encode($providers, JSON_UNESCAPED_UNICODE), $tmdbId]
        );
    }

    $log[] = $movieData['title'] ?? "#{$tmdbId}";
    $imported++;

    usleep(250_000);
}

echo json_encode([
    'ok'            => true,
    'page'          => $page,
    'total_pages'   => min($totalPages, 500),
    'total_results' => $totalResult,
    'imported'      => $imported,
    'movies'        => $log,
]);
