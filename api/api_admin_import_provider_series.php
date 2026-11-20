<?php
/**
 * API_ADMIN_IMPORT_PROVIDER_SERIES.PHP
 * Importe une page de séries depuis TMDB Discover pour un provider donné (région FR).
 * Pour chaque série : upsert metadata + Oracle + providers_data.
 * Traite 20 séries par appel (une page Discover). Appelé en boucle depuis le JS admin.
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
$providerId = (int)($body['provider_id'] ?? 350); // 350 = Apple TV+
$page       = max(1, (int)($body['page'] ?? 1));

if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY)) {
    echo json_encode(['ok' => false, 'error' => 'TMDB_API_KEY manquante']);
    exit;
}

// ── Discover : séries du provider sur FR ──────────────────────────────────────
$discoverUrl = "https://api.themoviedb.org/3/discover/tv"
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
    echo json_encode(['ok' => true, 'page' => $page, 'total_pages' => $totalPages, 'imported' => 0, 'series' => []]);
    exit;
}

$imported = 0;
$log      = [];

foreach ($results as $item) {
    $tmdbId = (int)($item['id'] ?? 0);
    if (!$tmdbId) continue;

    // ── Détails complets (total_seasons, genres, cast) ────────────────────────
    $seriesData = fetch_tmdb_series($tmdbId);
    if (!$seriesData) {
        $log[] = "#{$tmdbId} — fetch échoué, ignoré";
        usleep(200_000);
        continue;
    }

    // ── Upsert + Oracle (via upsert_series_metadata) ──────────────────────────
    upsert_series_metadata($tmdbId, $seriesData);

    // ── Providers FR ─────────────────────────────────────────────────────────
    $provUrl = "https://api.themoviedb.org/3/tv/{$tmdbId}/watch/providers?api_key=" . TMDB_API_KEY;
    $provRaw = @file_get_contents($provUrl);
    if ($provRaw) {
        $provData  = json_decode($provRaw, true);
        $providers = $provData['results']['FR'] ?? [];
        db_execute(
            'UPDATE series SET providers_data = ?, providers_updated_at = NOW() WHERE tmdb_id = ?',
            [json_encode($providers, JSON_UNESCAPED_UNICODE), $tmdbId]
        );
    }

    $log[] = $seriesData['title'];
    $imported++;

    usleep(250_000); // 250ms entre chaque série
}

echo json_encode([
    'ok'           => true,
    'page'         => $page,
    'total_pages'  => min($totalPages, 500), // TMDB cap à 500 pages
    'total_results'=> $totalResult,
    'imported'     => $imported,
    'series'       => $log,
]);
