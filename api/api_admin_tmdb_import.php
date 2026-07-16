<?php
/**
 * API_ADMIN_TMDB_IMPORT.PHP
 * Import complet depuis TMDB Discover — identique à une visite organique sur fiche.php.
 *   Films  : get_movie_smart()           → fetch + poster local + Oracle ADN + like_score
 *   Séries : fetch_tmdb_series() + Oracle→ mêmes chemins que fiche.php type=tv
 *
 * Traite UNE page Discover (20 items) par appel HTTP.
 * Le JS chaîne les appels automatiquement.
 * Admin uniquement.
 *
 * POST : type=movie|tv  phase=0-4  page=1-500
 */
require_once __DIR__ . '/../functions/utils.php';

header('Content-Type: application/json; charset=utf-8');
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST uniquement']);
    exit;
}

set_time_limit(120);

$type  = in_array($_POST['type'] ?? '', ['movie', 'tv']) ? $_POST['type'] : 'movie';
$phase = max(0, (int)($_POST['phase'] ?? 0));
$page  = max(1, (int)($_POST['page']  ?? 1));

// Phases : [date_from, date_to, vote_count_min]
$phases = [
    0 => ['1900-01-01', '1979-12-31', 20],
    1 => ['1980-01-01', '1999-12-31', 10],
    2 => ['2000-01-01', '2009-12-31', 10],
    3 => ['2010-01-01', '2019-12-31',  5],
    4 => ['2020-01-01', date('Y-m-d'),  3],
];

if (!isset($phases[$phase])) {
    echo json_encode(['success' => true, 'done' => true, 'inserted' => 0, 'skipped' => 0, 'errors' => 0]);
    exit;
}

[$dateFrom, $dateTo, $minVotes] = $phases[$phase];
$dateField   = $type === 'movie' ? 'primary_release_date' : 'first_air_date';
$regionParam = $type === 'movie' ? '&region=FR' : ''; // Films : sortis en France

$discoverUrl = "https://api.themoviedb.org/3/discover/{$type}"
    . "?api_key="       . TMDB_API_KEY
    . "&language=fr-FR"
    . "&sort_by=vote_count.desc"
    . "&vote_count.gte={$minVotes}"
    . "&{$dateField}.gte={$dateFrom}"
    . "&{$dateField}.lte={$dateTo}"
    . $regionParam
    . "&page={$page}";

$res = @file_get_contents($discoverUrl);
if (!$res) {
    echo json_encode(['success' => false, 'error' => 'TMDB Discover injoignable', 'phase' => $phase, 'page' => $page]);
    exit;
}

$data       = json_decode($res, true);
$totalPages = min((int)($data['total_pages'] ?? 1), 500);
$results    = $data['results'] ?? [];

$inserted = 0;
$skipped  = 0;
$errors   = 0;

foreach ($results as $item) {
    $tmdbId = (int)($item['id'] ?? 0);
    if (!$tmdbId) { $errors++; continue; }

    // ── FILMS ─────────────────────────────────────────────────────────────────
    if ($type === 'movie') {
        // Skip si déjà en base avec données complètes
        $existing = db_fetch_one(
            "SELECT tmdb_id FROM movies WHERE tmdb_id = ? AND synopsis IS NOT NULL AND synopsis != ''",
            [$tmdbId]
        );
        if ($existing) { $skipped++; continue; }

        // get_movie_smart = chemin identique à fiche.php :
        // fetch TMDB (détails + credits) + poster local + INSERT movies + Oracle (ADN + like_score)
        try {
            $movie = get_movie_smart($tmdbId);
            $movie ? $inserted++ : $errors++;
        } catch (Exception $e) {
            $errors++;
        }

        usleep(250000); // 250ms — 2 appels TMDB par film, reste sous 40 req/10s
    }

    // ── SÉRIES ────────────────────────────────────────────────────────────────
    else {
        // Skip si déjà en base
        $existing = db_fetch_one("SELECT id FROM series WHERE tmdb_id = ?", [$tmdbId]);
        if ($existing) { $skipped++; continue; }

        try {
            // 1. Fetch détails complets (même appel que fiche.php type=tv)
            $tmdbData = fetch_tmdb_series($tmdbId);
            if (!$tmdbData) { $errors++; usleep(100000); continue; }

            // 2. Upsert metadata + poster local (sync_series_poster inclus)
            upsert_series_metadata($tmdbId, $tmdbData);

            // 3. total_seasons — requis par apply_oracle_judgment_series
            db_execute(
                'UPDATE series SET total_seasons = ? WHERE tmdb_id = ? AND (total_seasons IS NULL OR total_seasons = 0)',
                [(int)($tmdbData['total_seasons'] ?? 0), $tmdbId]
            );

            // 4. Oracle — même appel que fiche.php isNewSeries
            $genres = array_filter(array_map(
                fn($g) => is_array($g) ? ($g['name'] ?? '') : (string)$g,
                $tmdbData['genres'] ?? []
            ));
            apply_oracle_judgment_series($tmdbId, array_values($genres));

            $inserted++;
        } catch (Exception $e) {
            $errors++;
        }

        // Oracle dort déjà 100ms entre les saisons — on ajoute juste une petite pause
        usleep(150000);
    }
}

// Page / phase suivante
$nextPage  = $page + 1;
$nextPhase = $phase;
if ($nextPage > $totalPages) {
    $nextPage  = 1;
    $nextPhase = $phase + 1;
}
$done = !isset($phases[$nextPhase]);

$phaseLabels = ['Classiques (−1980)', 'Années 80−90', 'Années 2000', 'Années 2010', '2020+'];

echo json_encode([
    'success'      => true,
    'done'         => $done,
    'inserted'     => $inserted,
    'skipped'      => $skipped,
    'errors'       => $errors,
    'phase'        => $phase,
    'phase_label'  => $phaseLabels[$phase] ?? '',
    'page'         => $page,
    'total_pages'  => $totalPages,
    'next_phase'   => $nextPhase,
    'next_page'    => $nextPage,
]);
