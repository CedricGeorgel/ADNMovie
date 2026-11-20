<?php
/**
 * API_ADMIN_ORACLE_LIKES.PHP
 * Recalcule le like_score Oracle depuis les votes TMDB pour films ET séries.
 * type=movie : met à jour ratings.like_score pour chaque film
 * type=series : met à jour season_ratings.like_score + recalcule series_ratings
 * Le JS chaîne les batches automatiquement.
 * Admin uniquement.
 */
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/api_oracle.php';

header('Content-Type: application/json; charset=utf-8');

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST uniquement']);
    exit;
}

set_time_limit(180);

$type   = $_POST['type']   ?? 'movie';
$offset = max(0, (int)($_POST['offset'] ?? 0));
$limit  = min(50, max(1, (int)($_POST['limit'] ?? 50)));

$pdo = getPDO();
_oracle_ensure_user($pdo);

$updated = 0;
$skipped = 0;
$errors  = 0;

// ── Films ─────────────────────────────────────────────────────────────────────
if ($type === 'movie') {
    $total  = (int)$pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
    $stmt   = $pdo->query("SELECT tmdb_id FROM movies ORDER BY tmdb_id LIMIT {$limit} OFFSET {$offset}");
    $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $tmdbId   = (int)$row['tmdb_id'];
        $response = @file_get_contents("https://api.themoviedb.org/3/movie/{$tmdbId}?api_key=" . TMDB_API_KEY);
        if (!$response) { $errors++; usleep(100000); continue; }

        $data      = json_decode($response, true);
        $voteAvg   = isset($data['vote_average']) ? (float)$data['vote_average'] : null;
        $voteCount = (int)($data['vote_count'] ?? 0);
        $likeScore = _oracle_vote_to_like_score($voteAvg, $voteCount);

        if ($likeScore === null) { $skipped++; usleep(25000); continue; }

        $pdo->prepare(
            "INSERT INTO ratings (user_id, movie_id, scores, like_score, is_liked, rating_weight, rated_at)
             VALUES (?, ?, NULL, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE like_score = VALUES(like_score), is_liked = VALUES(is_liked)"
        )->execute([ORACLE_USER_ID, $tmdbId, $likeScore, $likeScore > 0 ? 1 : 0, ORACLE_WEIGHT]);

        $updated++;
        usleep(50000);
    }

// ── Séries ────────────────────────────────────────────────────────────────────
} elseif ($type === 'series') {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM series WHERE total_seasons > 0")->fetchColumn();
    $stmt  = $pdo->query(
        "SELECT id, tmdb_id, total_seasons FROM series
         WHERE total_seasons > 0
         ORDER BY id LIMIT {$limit} OFFSET {$offset}"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $seriesId     = (int)$row['id'];
        $tmdbId       = (int)$row['tmdb_id'];
        $totalSeasons = (int)$row['total_seasons'];
        $seasonsDone  = 0;

        for ($sn = 1; $sn <= $totalSeasons; $sn++) {
            $url = "https://api.themoviedb.org/3/tv/{$tmdbId}/season/{$sn}?api_key=" . TMDB_API_KEY;
            $res = @file_get_contents($url);
            if (!$res) { usleep(150000); continue; }

            $data      = json_decode($res, true);
            $voteAvg   = isset($data['vote_average']) ? (float)$data['vote_average'] : null;
            $voteCount = (int)($data['vote_count'] ?? 0);
            $likeScore = _oracle_vote_to_like_score($voteAvg, $voteCount);

            if ($likeScore === null) { usleep(50000); continue; }

            // Met à jour le like_score Oracle existant, ou insère si absent
            $pdo->prepare(
                "INSERT INTO season_ratings
                    (user_id, series_id, season_number, scores, like_score, is_liked, rating_weight, rated_at)
                 VALUES (?, ?, ?, NULL, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    like_score = VALUES(like_score),
                    is_liked   = VALUES(is_liked)"
            )->execute([ORACLE_USER_ID, $seriesId, $sn, $likeScore, $likeScore > 0 ? 1 : 0, ORACLE_WEIGHT]);

            $seasonsDone++;
            usleep(80000);
        }

        if ($seasonsDone > 0) {
            _oracle_recalc_series_aggregate($pdo, ORACLE_USER_ID, $seriesId);
            $updated++;
        } else {
            $skipped++;
        }
    }
}

$newOffset = $offset + count($rows ?? []);

echo json_encode([
    'success' => true,
    'type'    => $type,
    'total'   => $total ?? 0,
    'offset'  => $newOffset,
    'done'    => $newOffset >= ($total ?? 0),
    'updated' => $updated,
    'skipped' => $skipped,
    'errors'  => $errors,
]);
