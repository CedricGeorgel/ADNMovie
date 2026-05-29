<?php
/**
 * API_IMPORT_CSV.PHP
 * Importe l'historique films depuis TMDB / Letterboxd / IMDb (CSV).
 * Les films sont marqués comme vus (sans scores DNA).
 */
session_start();
header('Content-Type: application/json');
set_time_limit(300);

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/api_tmdb.php';
require_once __DIR__ . '/../api/api_oracle.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId = $_SESSION['user_id'];

if (empty($_FILES['csv']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
    exit;
}

$handle = fopen($_FILES['csv']['tmp_name'], 'r');
if (!$handle) {
    echo json_encode(['success' => false, 'message' => 'Impossible de lire le fichier']);
    exit;
}

// ── Détection du format ───────────────────────────────────────────────────────
$rawHeaders = fgetcsv($handle);
if (!$rawHeaders) {
    echo json_encode(['success' => false, 'message' => 'Fichier CSV vide ou invalide']);
    exit;
}
$headers      = array_map('trim', $rawHeaders);
$headersLower = array_map('strtolower', $headers);

$format = 'tmdb';
if (in_array('const', $headersLower))                                                                                      $format = 'imdb';
elseif (in_array('rating10', $headersLower) || (in_array('title', $headersLower) && in_array('directors', $headersLower))) $format = 'sensboxd';
elseif (in_array('letterboxd uri', $headersLower) || in_array('name', $headersLower) && in_array('year', $headersLower))   $format = 'letterboxd';

function csvCol(array $row, array $headers, string ...$names): ?string {
    foreach ($names as $name) {
        $idx = array_search(strtolower($name), array_map('strtolower', $headers));
        if ($idx !== false && isset($row[$idx]) && trim($row[$idx]) !== '') {
            return trim($row[$idx]);
        }
    }
    return null;
}

function tmdbSearch(string $title, ?int $year, string $apiKey): int {
    $q = urlencode($title);
    $y = $year ? "&year={$year}" : '';
    $res = @file_get_contents("https://api.themoviedb.org/3/search/movie?api_key={$apiKey}&query={$q}{$y}&language=fr-FR");
    if (!$res) return 0;
    $data = json_decode($res, true);
    return (int)(($data['results'][0]['id'] ?? 0));
}

function mapRating(?string $raw, float $max): ?int {
    if ($raw === null || $raw === '') return null;
    $r = (float)str_replace(',', '.', $raw);
    if ($r <= 0) return null;
    return max(-10, min(10, (int)round(($r / $max * 20) - 10)));
}

// ── Traitement ────────────────────────────────────────────────────────────────
$apiKey   = TMDB_API_KEY;
$imported = $skipped = $notFound = $total = 0;

while (($row = fgetcsv($handle)) !== false) {
    if (count(array_filter($row)) === 0) continue;
    $total++;

    $tmdbId    = 0;
    $likeScore = null;

    if ($format === 'imdb') {
        $titleType = csvCol($row, $headers, 'Title Type');
        if ($titleType && !in_array(strtolower($titleType), ['movie', 'tvmovie', ''])) {
            $skipped++;
            continue;
        }
        $imdbId    = csvCol($row, $headers, 'Const');
        $likeScore = mapRating(csvCol($row, $headers, 'Your Rating'), 10);

        if ($imdbId) {
            $res = @file_get_contents("https://api.themoviedb.org/3/find/{$imdbId}?api_key={$apiKey}&external_source=imdb_id");
            if ($res) {
                $data   = json_decode($res, true);
                $tmdbId = (int)(($data['movie_results'][0]['id'] ?? 0));
            }
        }
        if (!$tmdbId) {
            $title = csvCol($row, $headers, 'Title', 'Original Title');
            $year  = (int)(csvCol($row, $headers, 'Year') ?? 0);
            if ($title) $tmdbId = tmdbSearch($title, $year ?: null, $apiKey);
        }

    } elseif ($format === 'sensboxd') {
        $title     = csvCol($row, $headers, 'Title');
        $year      = (int)(csvCol($row, $headers, 'Year') ?? 0);
        $likeScore = mapRating(csvCol($row, $headers, 'Rating10'), 10);
        if ($title) $tmdbId = tmdbSearch($title, $year ?: null, $apiKey);

    } elseif ($format === 'letterboxd') {
        $title     = csvCol($row, $headers, 'Name');
        $year      = (int)(csvCol($row, $headers, 'Year') ?? 0);
        $likeScore = mapRating(csvCol($row, $headers, 'Rating'), 5);
        if ($title) $tmdbId = tmdbSearch($title, $year ?: null, $apiKey);

    } else { // tmdb
        $tmdbId    = (int)(csvCol($row, $headers, 'tmdb_id', 'id') ?? 0);
        $likeScore = mapRating(csvCol($row, $headers, 'Rating', 'Your Rating'), 10);
        if (!$tmdbId) {
            $title = csvCol($row, $headers, 'Name', 'Title');
            if ($title) $tmdbId = tmdbSearch($title, null, $apiKey);
        }
    }

    if (!$tmdbId) { $notFound++; continue; }

    $isLiked = $likeScore !== null && $likeScore > 0;

    get_movie_smart($tmdbId); // fetch titre, poster, genres depuis TMDB et upsert en BDD
    db_execute(
        'INSERT INTO ratings (user_id, movie_id, like_score, is_liked, rated_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             like_score = IF(scores IS NULL, VALUES(like_score), like_score),
             is_liked   = IF(scores IS NULL, VALUES(is_liked),   is_liked)',
        [$userId, $tmdbId, $likeScore, $isLiked ? 1 : 0]
    );
    $imported++;

    // Pause toutes les 45 requêtes pour respecter le rate-limit TMDB (50 req/s)
    if ($total % 45 === 0) usleep(1100000);
}

fclose($handle);

echo json_encode([
    'success'   => true,
    'format'    => $format,
    'total'     => $total,
    'imported'  => $imported,
    'skipped'   => $skipped,
    'not_found' => $notFound,
]);
